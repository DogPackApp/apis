<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Product\Item;
use App\Models\Store\Store;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProductSyncController extends Controller
{
    public function __construct(private readonly ProductService $productService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source_id' => ['required', 'string'],
            'status' => ['required', Rule::in(array_keys(Item::STATUS))],
            'about.title' => ['required', 'string'],
            'price_inventory.domestic_price' => ['nullable', 'numeric', 'required_if:item_variations.vary_by.price,false'],
            'item_variations' => ['required', 'array'],
            'item_variations.variants' => ['required', 'array'],
            'item_variations.variants.*.name' => ['required_with:item_variations.variants', 'string'],
            'item_variations.variants.*.options' => ['required_with:item_variations.variants.*.name', 'array', 'min:1'],
            'item_variations.variants.*.options.*' => ['required_with:item_variations.variants.*.options', 'string'],
            'item_variations.combination' => ['required', 'array'],
            'item_variations.combination.*.shopify_id' => ['required', 'string'],
            'item_variations.combination.*.variants' => ['required_with:item_variations.combination', 'array'],
            'item_variations.combination.*.variants.*.type' => ['required_with:item_variations.combination.*.variants', 'string'],
            'item_variations.combination.*.variants.*.value' => ['required_with:item_variations.combination.*.variants.*.type', 'string'],
            'about.type' => ['sometimes', 'string', Rule::in(array_keys(Item::TYPES))],
            'about.description' => ['sometimes'],
            'about.origin' => ['sometimes'],
            'about.customizations' => ['sometimes', 'array'],
            'about.customizations.enabled' => ['required_with:about.customizations', 'boolean'],
            'about.customizations.description' => ['nullable', 'string'],
            'about.customizations.limit' => ['required_with:about.customizations', 'integer', 'min:1', 'max:1024'],
            'about.customizations.attach_file' => ['nullable', 'boolean'],
            'about.images' => ['nullable', 'array', 'max:250'],
            'about.images.*.url' => ['sometimes', 'string'],
            'about.images.*.media_type' => ['sometimes', 'string', Rule::in(['IMAGE', 'VIDEO', 'EXTERNAL_VIDEO', 'MODEL_3D'])],
            'price_inventory.allow_overselling' => ['sometimes', 'boolean'],
            'price_inventory.is_global_pricing' => ['sometimes', 'boolean'],
            'price_inventory.global_price' => ['nullable', 'numeric', 'required_if:price_inventory.is_global_pricing,true', 'required_if:item_variations.vary_by.price,false'],
            'price_inventory.sku' => ['nullable', 'string'],
            'price_inventory.gtin' => ['nullable', 'string'],
            'price_inventory.weight' => ['nullable', 'numeric'],
            'price_inventory.unit' => ['nullable', 'string', Rule::in(array_keys(Item::ITEM_WEIGHT))],
            'price_inventory.stock_quantity' => ['nullable', 'numeric'],
            'category_tags.category' => ['sometimes', 'uuid', 'exists:categories,uuid'],
            'category_tags.tags' => ['nullable', 'array'],
            'category_tags.tags.*' => ['string'],
            'section' => ['nullable', 'array'],
            'section.*' => ['uuid'],
            'item_variations.combination.*.domestic_price' => ['nullable', 'numeric', 'min:0'],
            'item_variations.combination.*.global_price' => ['nullable', 'numeric', 'min:0'],
            'item_variations.combination.*.sku' => ['nullable', 'string'],
            'item_variations.combination.*.gtin' => ['nullable', 'string'],
            'item_variations.combination.*.quantity' => ['nullable', 'integer'],
            'item_variations.combination.*.weight' => ['nullable', 'numeric', 'min:0'],
            'item_variations.combination.*.is_visible' => ['sometimes', 'bool'],
            'item_variations.combination.*.image' => ['nullable'],
            'item_variations.combination.*.image.*.url' => ['sometimes', 'string'],
            'item_variations.combination.*.image.*.media_type' => ['sometimes', 'string'],
            'item_variations.combination.*.allow_overselling' => ['sometimes', 'bool'],
            'item_variations.vary_by' => ['nullable', 'array'],
            'item_variations.vary_by.sku' => ['required_with:item_variations.vary_by', 'boolean'],
            'item_variations.vary_by.quantity' => ['required_with:item_variations.vary_by', 'boolean'],
            'item_variations.vary_by.price' => ['required_with:item_variations.vary_by', 'boolean'],
            'item_variations.vary_by.weight' => ['required_with:item_variations.vary_by', 'boolean'],
            'is_bundled_product' => ['required', 'boolean'],
            'sizing_chart' => ['nullable', 'array'],
            'sizing_chart.source' => ['required_with:sizing_chart', 'string', Rule::in(['metafield', 'kiwi', 'description'])],
            'sizing_chart.charts' => ['required_with:sizing_chart', 'array'],
            'sizing_chart.charts.*.id' => ['nullable', 'string'],
            'sizing_chart.charts.*.name' => ['nullable', 'string'],
            'sizing_chart.charts.*.image_url' => ['nullable', 'string'],
            'sizing_chart.charts.*.html' => ['nullable', 'string'],
            'sizing_chart.charts.*.table_html' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $maxExecutionSeconds = max(30, (int) config('shopify.product_sync_max_execution_seconds', 120));
        set_time_limit($maxExecutionSeconds);

        Log::channel('shopify')->info('Product Sync Request: '.json_encode($request->all()));
        $product = $validator->validated();
        $store = $this->storeFromHeader($request);

        if ($store === null) {
            return response()->json('Unauthorized.', Response::HTTP_UNAUTHORIZED);
        }

        $maxAttempts = 3;
        $attempt = 0;

        do {
            $attempt++;
            DB::beginTransaction();
            try {
                $this->productService->createProductFromShopify($store, $product);

                DB::commit();

                return response()->json([], Response::HTTP_CREATED);
            } catch (Throwable $e) {
                Log::channel('shopify')
                    ->error('Title '.$product['about']['title'].' => attempt => '.$attempt);
                DB::rollBack();

                if ($attempt === $maxAttempts) {
                    $message = app()->environment() !== 'production'
                        ? $e->getMessage()
                        : 'Something went wrong';

                    return response()->json([
                        'errors' => [
                            'message' => $message,
                        ],
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                usleep(500000);
            }
        } while ($attempt < $maxAttempts);

        return response()->json([
            'errors' => [
                'message' => 'Something went wrong',
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function storeFromHeader(Request $request): ?Store
    {
        return Store::query()->where('uuid', $request->header('X-Shopify-DP-Store'))->first();
    }
}
