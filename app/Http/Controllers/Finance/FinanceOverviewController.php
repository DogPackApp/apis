<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\PayoutResource;
use App\Models\Order\Payout;
use App\Models\Store\Store;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class FinanceOverviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse|AnonymousResourceCollection
    {
        $validator = Validator::make($request->all(), [
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'transaction_status' => ['nullable', Rule::in(Payout::STATUS)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_with:start_date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $storeId = resolve(Store::class)->id;

        $payout = Payout::query()
            ->where('store_id', $storeId)
            ->when(
                $request->filled('transaction_status') && $request->input('transaction_status') !== 'all',
                function ($query) use ($request) {
                    $query->where('status', $request->input('transaction_status'));
                }
            )
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($q) use ($search) {
                    $q->where('stripe_txn_id', 'like', '%'.$search.'%')
                        ->orWhere('uuid', 'like', '%'.$search.'%');
                });
            })
            ->when($request->filled('start_date'), function ($query) use ($request) {
                $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
                $endDate = Carbon::parse($request->input('end_date'))->endOfDay();

                $query->whereBetween('payout_date', [$startDate, $endDate]);
            })
            ->orderBy('payout_date', 'desc')
            ->paginate($validator->validated()['per_page'] ?? 10);

        $depositedAmount = Payout::query()
            ->where('store_id', $storeId)
            ->where('status', Payout::DEPOSITED)
            ->sum('amount');

        $upcomingAmount = Payout::query()
            ->where('store_id', $storeId)
            ->where('status', Payout::UPCOMING)
            ->sum('amount');

        return PayoutResource::collection($payout)->additional([
            'deposited' => $depositedAmount / 100,
            'upcoming' => $upcomingAmount / 100,
        ]);
    }
}
