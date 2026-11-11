<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Jobs\Finance\ExportFinancePayoutsJob;
use App\Models\Order\Payout;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FinanceOverviewExportCsvController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request)
    {
        try {
            $store = resolve(Store::class);

            $validator = Validator::make($request->all(), [
                'payouts' => ['required', 'array', 'min:1'],
                'payouts.*' => [
                    'required',
                    'uuid',
                    Rule::exists((new Payout)->getTable(), 'uuid')->where('store_id', $store->id),
                ],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $payload = [
                'store_id' => $store->id,
                'payout_uuids' => $validator->validated()['payouts'],
            ];

            if ($this->shouldRunExportSynchronously()) {
                ExportFinancePayoutsJob::dispatchSync($payload);
            } else {
                ExportFinancePayoutsJob::dispatch($payload)->onQueue('mkt-medium');
            }

            return $this->successResponse(
                'Finance export CSV file is being prepared. You will receive an email when it is ready.'
            );
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function shouldRunExportSynchronously(): bool
    {
        if (config('queue.default') === 'sync') {
            return true;
        }

        return ! app()->environment('production')
            && in_array(config('queue.default'), ['database', 'redis'], true);
    }
}
