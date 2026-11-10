<?php

namespace App\Http\Controllers\Product\Bulk;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Models\Product\Item;
use App\Models\Store\Store;
use App\Repository\Product\ProductRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ProductBulkUpdateController extends Controller
{
    use RespondsWithSellerJson;

    protected ProductRepository $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function __invoke(Request $request): Response
    {

        $productsFromRequest = $request->input('products', []);

        if (empty($productsFromRequest)) {
            return $this->errorResponse('No products provided', Response::HTTP_BAD_REQUEST);
        }

        $store = resolve(Store::class);
        $validProducts = [];
        foreach ($productsFromRequest as $product) {
            if (isset($product['status']) && $product['status'] == Item::STATUS_ACTIVE) {
                $validator = Validator::make($product, [
                    'status' => ['required', Rule::in(array_keys(Item::STATUS))],
                    'uuid' => ['required', 'uuid', 'exists:items,uuid'],
                    'title' => ['sometimes', 'string'],
                    'description' => ['sometimes', 'string'],
                    'prices' => ['sometimes', 'array'],
                    'prices.uuid' => ['sometimes', 'uuid'],
                    'prices.sku' => ['nullable', 'string'],
                    'prices.gtin' => ['nullable', 'string'],
                    'prices.is_global_pricing' => ['nullable', 'boolean'],
                    'prices.domestic_price' => ['nullable', 'numeric', 'gt:0'],
                    'prices.global_price' => ['nullable', 'numeric'],
                    'prices.stock_quantity' => ['nullable', 'numeric'],
                    'prices.allow_overselling' => ['nullable', 'boolean'],
                    'prices.weight' => ['nullable', 'numeric'],
                    'prices.unit' => ['nullable', 'string', Rule::in(array_keys(Item::ITEM_WEIGHT))],
                    'origin' => ['sometimes', 'string'],
                    'customization' => ['sometimes', 'array'],
                    'customization.is_enabled' => ['required_with:customization', 'boolean'],
                    'customization.text' => ['nullable', 'string'],
                    'customization.text_limit' => ['nullable', 'integer', 'nullable', 'max:1024'],
                    'customization.image' => ['sometimes', 'boolean'],
                    'customization.description_enabled' => ['nullable', 'boolean'],
                    'category' => ['sometimes', 'uuid', 'exists:categories,uuid'],
                    'policy' => ['sometimes', 'uuid', 'exists:policies,uuid'],
                    'tags' => ['nullable', 'string'],
                    'sections' => ['nullable', 'array'],
                    'medias' => ['nullable', 'array'],
                    'medias.*' => ['nullable', 'uuid', 'exists:galleries,uuid'],
                    'discount' => ['sometimes', 'array'],
                    'discount.value' => ['nullable', 'numeric', 'gt:0', 'between:1,100'],
                    'discount.is_limited' => ['sometimes', 'boolean'],
                    'discount.start_date' => ['nullable', 'date', 'nullable'],
                    'discount.end_date' => ['nullable', 'date', 'after:discount.start_date', 'nullable'],
                    'combinations' => ['sometimes', 'array'],
                    'combinations.*' => ['sometimes', 'array'],
                    'combinations.*.uuid' => ['sometimes', 'uuid', 'exists:item_variants,uuid'],
                    'combinations.*.sku' => ['nullable', 'string'],
                    'combinations.*.gtin' => ['nullable', 'string'],
                    'combinations.*.weight' => ['nullable', 'numeric'],
                    'combinations.*.domestic_price' => ['nullable', 'numeric', 'gt:0'],
                    'combinations.*.stock_quantity' => ['nullable', 'numeric'],
                    'combinations.*.allow_overselling' => ['nullable', 'boolean'],
                    'combinations.*.is_visible' => ['nullable', 'boolean'],
                    'combinations.*.image' => ['nullable', 'array'],
                    'combinations.*.image.uuid' => ['nullable', 'uuid', 'exists:galleries,uuid'],
                    'combinations.*.image.url' => ['nullable', 'string'],
                ]);
                if ($validator->fails()) {
                    return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $validProducts[] = $validator->validated();
            } else {
                $validProducts[] = $product;
            }
        }
        $formattedProducts = $this->formatProducts($validProducts);
        $updatedProducts = [];
        DB::beginTransaction();
        try {
            foreach ($formattedProducts as $index => $product) {
                $item = Item::where('uuid', $product['uuid'])->first();
                if (! $item) {
                    return $this->errorResponse('Product not found', Response::HTTP_NOT_FOUND);
                }

                $updatedProducts[] = $this->productRepository->updateBulk($store, $item, $product);
            }
            DB::commit();

            return $this->successResponseWithData($updatedProducts, 'Products updated successfully');
        } catch (Exception $e) {
            DB::rollBack();

            return $this->errorResponse($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function formatProducts(array $products): array
    {
        return array_map(fn ($product) => $this->formatProduct($product), $products);
    }

    private function formatProduct(array $product): array
    {
        return array_filter([
            'uuid' => $product['uuid'],
            'status' => $product['status'] ?? null,
            'category_tags' => $this->formatCategoryTags($product),
            'section' => $product['sections'] ?? null,
            'policy' => $product['policy'] ?? null,
            'discount' => $product['discount'] ?? null,
            'price_inventory' => $product['prices'] ?? null,
            'about' => $this->formatAbout($product),
            'item_variations' => $this->formatVariations($product),
        ], fn ($value) => $value !== null);
    }

    private function formatCategoryTags(array $product): ?array
    {
        $categoryTags = array_filter([
            'category' => $product['category'] ?? null,
            'tags' => ! empty($product['tags']) ? explode(',', $product['tags']) : null,
        ], fn ($value) => $value !== null);

        return ! empty($categoryTags) ? $categoryTags : null;
    }

    private function formatAbout(array $product): array
    {
        return array_filter([
            'title' => $product['title'] ?? null,
            'type' => $product['type'] ?? null,
            'description' => $product['description'] ?? null,
            'origin' => $product['origin'] ?? null,
            'images' => $product['medias'] ?? null,
            'customizations' => $this->formatCustomizations($product),
        ], fn ($value) => $value !== null);
    }

    private function formatCustomizations(array $product): ?array
    {
        if (! isset($product['customization'])) {
            return null;
        }

        $customization = $product['customization'];

        $formatted = array_filter([
            'enabled' => $customization['is_enabled'] ?? null,
            'description' => $customization['text'] ?? null,
            'attach_file' => $customization['image'] ?? null,
            'limit' => $customization['text_limit'] ?? null,
            'description_enabled' => $customization['description_enabled'] ?? null,
        ], fn ($value) => $value !== null);

        return ! empty($formatted) ? $formatted : null;
    }

    private function formatVariations(array $product): ?array
    {
        if (empty($product['combinations'])) {
            return null;
        }

        return ['combination' => $product['combinations']];
    }
}
