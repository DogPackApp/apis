<?php

namespace App\Http\Controllers\Product\Import;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Jobs\Product\ImportValidateProductJob;
use App\Models\Store\Store;
use App\Services\Import\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CSVImportController extends Controller
{
    use RespondsWithSellerJson;

    // CSV Stored Here => uploads/csv/{$store->slug}/csv-".time().".csv
    public function __invoke(Request $request, ImportService $importService)
    {
        try {
            //    $store = resolve(Store::class);
            //    $importService->setStore($store);
            //    ImportValidateProductJob::dispatchSync(
            //        "7f4533ba-3a4f-4ddb-a291-99c2f7b8369c",
            //        resolve(Store::class),
            //        $importService
            //    );

            //    dd('here');

            // add a validation here to get the csv file in the `csv` key and store and limit of csv is 10MB
            // store it in our mps3 disk and return the url of the file
            $validator = $this->validateRequest($request);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors(), 422);
            }

            $file = $request->file('csv');

            $store = resolve(Store::class);
            // 2. Define file path
            $filename = "uploads/csv/{$store->slug}/csv-".time().'.csv';

            Storage::disk('mps3')->put($filename, $file->get());
            $batchId = Str::uuid();
            // needs a csv parse which reads the file and returns the data in an array
            $importService
                ->parseCSV($file)
                ->import($batchId, $store);

            ImportValidateProductJob::dispatch($batchId, $store)->onQueue('mkt-high');

            // 4. Make it publicly visible
            Storage::disk('mps3')->setVisibility($filename, 'public');

            // 5. Get public URL
            $url = Storage::disk('mps3')->url($filename);

            return response()->json([
                'batchId' => $batchId,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Something went wrong, please contact support', 500);
        }
    }

    public function validateRequest(Request $request)
    {
        return Validator::make($request->all(), [
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);
    }
}
