<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Jobs\Order\ExportOrdersJob;
use App\Models\Order\ExportOrders;
use App\Models\Order\StoreOrder;
use App\Models\Store\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderExportCsvController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request)
    {
        try {

            // validate the request
            $validator = Validator::make($request->all(), [
                'search' => ['nullable', 'string', 'max:255'],
                'fulfilment_status' => ['nullable', Rule::in(StoreOrder::$fulfilmentStatus)],
                'order_status' => ['nullable', Rule::in(StoreOrder::$orderStatus)],
                'payment_status' => ['nullable', Rule::in(StoreOrder::$paymentStatus)],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'required_with:start_date', 'date'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $storeId = resolve(Store::class)->id;
            $validated = [];
            foreach ($validator->validated() as $key => $value) {
                if ($value) {
                    $validated[$key] = $value;
                }
            }

            // create a batch id
            $batchID = Str::uuid()->toString();

            // create a export product record in batch
            $exportOrders = [
                'batch_id' => $batchID,
                'store_id' => $storeId,
                'filters' => ! empty($validated) ? json_encode($validated) : null,
            ];

            // create a export product record in batch
            ExportOrders::insert($exportOrders);

            // dispatch the job to export the orders
            ExportOrdersJob::dispatch([
                'batch_id' => $batchID,
                'store_id' => $storeId,
                'filters' => $validated,
            ]);

            return $this->successResponse('Order export CSV file is being prepared. You will receive an email when it is ready.');

        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }
}
