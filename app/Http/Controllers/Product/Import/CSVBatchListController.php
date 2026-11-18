<?php

namespace App\Http\Controllers\Product\Import;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Models\Product\ImportProduct;
use Exception;
use Illuminate\Http\JsonResponse;

class CSVBatchListController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(string $batchId): JsonResponse
    {
        try {

            $errors = [];
            $all = ImportProduct::query()
                ->with('variants')
                ->where('batch_id', $batchId)
                ->get();

            foreach ($all as $item) {
                if ($item->variants->count() > 0) {
                    foreach ($item->variants as $variant) {
                        $errors[] = $variant->errors;
                    }
                } else {
                    $errors[] = ($item->errors);
                }
            }

            return response()->json([
                'data' => array_values(array_filter($errors)),
            ]);

        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
