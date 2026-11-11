<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\PayoutResource;
use App\Models\Order\Payout;
use App\Models\Order\PayoutDetail;
use App\Models\Order\StoreOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class FinanceOverviewDetailController extends Controller
{
    public function __invoke(Request $request, Payout $payout): PayoutResource|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', Rule::in(array_merge(['all'], StoreOrder::$paymentStatus))],
            'fulfilment_status' => ['nullable', Rule::in(StoreOrder::$fulfilmentStatus)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_with:start_date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $details = PayoutDetail::query()
            ->where('payout_id', $payout->id)
            ->with(['order.orderBilling', 'order.customer'])
            ->whereHas('order', fn ($orderQuery) => $this->applyOrderFilters($orderQuery, $request))
            ->join('store_orders', 'payout_details.store_order_id', '=', 'store_orders.id')
            ->orderByDesc('store_orders.created_at')
            ->select('payout_details.*')
            ->paginate($validator->validated()['per_page'] ?? 100);

        $payout->loadMissing('store.country');
        $payout->setRelation('details', $details);

        return (new PayoutResource($payout))->additional([
            'meta' => [
                'current_page' => $details->currentPage(),
                'per_page' => $details->perPage(),
                'total' => $details->total(),
                'last_page' => $details->lastPage(),
            ],
        ]);
    }

    private function applyOrderFilters($orderQuery, Request $request): void
    {
        $orderQuery
            ->when(
                $request->filled('status') && $request->input('status') !== 'all',
                fn ($q) => $q->where('payment_status', $request->input('status'))
            )
            ->when(
                $request->filled('fulfilment_status'),
                fn ($q) => $q->where('fulfilment_status', $request->input('fulfilment_status'))
            )
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');

                $q->where(function ($q) use ($search) {
                    $q->when(Str::startsWith($search, '#'), function ($q) use ($search) {
                        $q->where('order_no', 'like', '%'.Str::after($search, '#').'%');
                    }, function ($q) use ($search) {
                        $q->where('order_no', 'like', "%{$search}%")
                            ->orWhereHas('customer', function ($q2) use ($search) {
                                $q2->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
                });
            })
            ->when($request->filled('start_date'), function ($q) use ($request) {
                $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
                $endDate = Carbon::parse($request->input('end_date'))->endOfDay();

                $q->whereBetween('created_at', [$startDate, $endDate]);
            });
    }
}
