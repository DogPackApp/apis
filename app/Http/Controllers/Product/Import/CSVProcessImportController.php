<?php

namespace App\Http\Controllers\Product\Import;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Jobs\Product\ImportProcessCSVJob;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CSVProcessImportController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(string $batchId): JsonResponse
    {
        try {
            $startTime = now();
            $validator = Validator::make(['batchId' => $batchId], [
                'batchId' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors(), 422);
            }

            ImportProcessCSVJob::dispatch($batchId, resolve(Store::class)?->id, $startTime)->onQueue('mkt-high');

            return $this->successResponse('Initiated Importing of Products...');

        } catch (Exception $e) {
            return $this->errorResponse($e, 500);
        }
    }
}
