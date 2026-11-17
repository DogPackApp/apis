<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Item;
use App\Models\Store\Store;
use App\Models\Store\StoreShopifyConnection;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ProductBulkMoveController extends Controller
{
    public function __invoke(Request $request): JsonResponse|Response
    {
        $validator = Validator::make($request->all(), [
            'products' => ['required', 'array'],
            'products.*' => ['required', 'uuid', 'exists:items,uuid'],
            'status' => ['required', Rule::in([Item::STATUS_ACTIVE, Item::STATUS_DRAFT])],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();

            if ($request->input('status') === Item::STATUS_DRAFT) {
                $this->toDraft($request->input('products.*'));
                DB::commit();

                return response()->json([], Response::HTTP_NO_CONTENT);
            }

            $invalidItems = $this->toActive($request->input('products.*'));

            DB::commit();

            if (empty($invalidItems)) {
                return response()->json([], Response::HTTP_NO_CONTENT);
            }

            return response()->json(['products' => $invalidItems], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'errors' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function toDraft($uuids): void
    {
        Item::query()
            ->whereIn('uuid', $uuids)
            ->update([
                'status' => Item::STATUS[Item::STATUS_DRAFT],
                'manually_deactivated' => true,
            ]);
    }

    public function toActive($uuids): array
    {
        $shopifyProductsUUIDs = Item::query()
            ->select('uuid', 'source_id')
            ->whereIn('uuid', $uuids)
            ->whereNotNull('source_id')
            ->get()
            ->pluck('uuid')
            ->toArray();

        $dontMoveProducts = $this->handleShopifyProducts($shopifyProductsUUIDs);

        $countable = Item::query()
            ->select('uuid', 'source_id')
            ->whereIn('uuid', $uuids)
            ->where('is_valid', 0)
            ->whereNull('source_id')
            ->get()
            ->pluck('uuid')
            ->toArray();

        Item::query()
            ->whereIn('uuid', $uuids)
            ->where('is_valid', 1)
            ->whereNull('source_id')
            ->update([
                'status' => Item::STATUS[Item::STATUS_ACTIVE],
                'manually_deactivated' => false,
            ]);

        $all = array_unique(array_merge($dontMoveProducts, $countable));

        $new = [];

        foreach ($all as $uuid) {
            $new[] = ['uuid' => $uuid];
        }

        return $new;
    }

    private function handleShopifyProducts($uuids): array
    {
        $store = resolve(Store::class);

        $shopifyItems = Item::query()
            ->select('uuid', 'source_id')
            ->whereIn('uuid', $uuids)
            ->whereNotNull('source_id')
            ->get();

        if ($shopifyItems->isEmpty()) {
            return [];
        }

        $shopifyProductIds = $shopifyItems->pluck('source_id')->toArray();

        $useNewShopifyPipeline = StoreShopifyConnection::on('mysql_reader')
            ->where('store_id', $store->id)
            ->exists();

        if (empty($useNewShopifyPipeline)) {
            return [];
        }

        $statusResponse = $this->fetchProductStatusesFromDragon($store, $shopifyProductIds);

        $onlyActiveProducts = $this->extractActiveShopifyProductIds($statusResponse);

        $onlyActiveProductsUUID = [];

        if (! empty($onlyActiveProducts)) {
            Item::query()
                ->whereIn('source_id', $onlyActiveProducts)
                ->update([
                    'status' => Item::STATUS[Item::STATUS_ACTIVE],
                    'manually_deactivated' => false,
                ]);

            $onlyActiveProductsUUID = Item::query()
                ->select(['uuid'])
                ->whereIn('source_id', $onlyActiveProducts)
                ->get()
                ->pluck('uuid')
                ->toArray();
        }

        return array_diff($uuids, $onlyActiveProductsUUID);
    }

    private function fetchProductStatusesFromDragon(Store $store, array $productIds): array
    {
        $payload = [
            'shopData' => [
                'storeUuid' => $store->uuid,
            ],
            'productIds' => $productIds,
        ];

        $headers = [
            'accept' => 'application/json',
            'X-DRAGON-ACCEPT' => 'Bearer '.config('shopify.token'),
        ];

        $response = Http::withHeaders($headers)
            ->post(config('shopify.url').'/products/status', $payload);

        $body = $response->json();

        Log::info('Shopify Dragon Product Status Response: ', [
            'payload' => $payload,
            'response' => $body,
        ]);

        if (! $response->successful() || ! is_array($body)) {
            throw new Exception('Failed to fetch Shopify product statuses.');
        }

        return $body;
    }

    private function extractActiveShopifyProductIds(array $statusResponse): array
    {
        $products = $statusResponse['data']['products']
            ?? $statusResponse['products']
            ?? $statusResponse['data']
            ?? [];

        if (! is_array($products)) {
            return [];
        }

        $onlyActiveProducts = [];

        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $status = $product['status'] ?? $product['productStatus'] ?? null;

            if ($status !== Item::STATUS_ACTIVE) {
                continue;
            }

            $productId = $product['id'] ?? $product['productId'] ?? null;

            if ($productId !== null) {
                $onlyActiveProducts[] = $productId;
            }
        }

        return $onlyActiveProducts;
    }
}
