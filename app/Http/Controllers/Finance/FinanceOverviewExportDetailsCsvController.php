<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Jobs\Finance\ExportFinancePayoutDetailsJob;
use App\Models\Order\Payout;
use App\Models\Order\PayoutDetail;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FinanceOverviewExportDetailsCsvController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request)
    {
        try {
            $store = resolve(Store::class);

            $validator = Validator::make($request->all(), [
                'payout_uuid' => [
                    'required',
                    'uuid',
                    Rule::exists((new Payout)->getTable(), 'uuid')->where('store_id', $store->id),
                ],
                'orders' => ['required', 'array', 'min:1'],
                'orders.*' => [
                    'required',
                    'uuid',
                    Rule::exists((new PayoutDetail)->getTable(), 'uuid'),
                ],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $validated = $validator->validated();

            $payout = Payout::on('mysql_reader')
                ->where('uuid', $validated['payout_uuid'])
                ->where('store_id', $store->id)
                ->first();

            if (! $payout) {
                return $this->errorResponse('Payout not found', Response::HTTP_NOT_FOUND);
            }

            $validOrderCount = PayoutDetail::on('mysql_reader')
                ->where('payout_id', $payout->id)
                ->whereIn('uuid', $validated['orders'])
                ->count();

            if ($validOrderCount !== count($validated['orders'])) {
                return $this->errorResponse(
                    'One or more selected orders do not belong to this payout.',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $payload = [
                'store_id' => $store->id,
                'payout_uuid' => $validated['payout_uuid'],
                'detail_uuids' => $validated['orders'],
            ];

            if ($this->shouldRunExportSynchronously()) {
                ExportFinancePayoutDetailsJob::dispatchSync($payload);
            } else {
                ExportFinancePayoutDetailsJob::dispatch($payload)->onQueue('mkt-medium');
            }

            return $this->successResponse(
                'Finance details export CSV file is being prepared. You will receive an email when it is ready.'
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
