<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Jobs\ExportProductsJob;
use App\Models\Product\ExportProduct;
use App\Models\Product\Item;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductExportCsvController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => ['nullable', Rule::in(values: array_keys(Item::STATUS))],
                'products' => ['nullable', 'array'],
                'products.*' => ['nullable', 'uuid', 'exists:items,uuid'],
            ]);
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $batchID = Str::uuid()->toString();

            $exportProducts = array_map(fn ($product) => [
                'batch_id' => $batchID,
                'product_uuid' => $product,
            ], $validator->validated()['products'] ?? []);
            $exportProducts = count($exportProducts) > 0 ? $exportProducts : [
                'batch_id' => $batchID,
                'product_uuid' => null,
            ];

            DB::beginTransaction();
            ExportProduct::insert($exportProducts);
            DB::commit();

            $store = resolve(Store::class);
            ExportProductsJob::dispatch([
                'batch_id' => $batchID,
                'status' => $validator->validated()['status'] ?? null,
                'store_id' => $store ? $store->id : null,
            ])->onQueue('mkt-medium');

            return response()->json([
                'message' => 'Product export CSV file is being prepared. You will receive an email when it is ready.',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            if (app()->environment() !== 'production') {
                $message = $e->getMessage();
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['errors' => $message]);
        }
    }
}
