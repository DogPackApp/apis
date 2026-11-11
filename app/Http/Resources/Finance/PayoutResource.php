<?php

namespace App\Http\Resources\Finance;

use App\Services\Order\PayoutService;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $filter['status'] = $request->input('status') ?? 'all';

        $all = collect(PayoutService::calculate($this->resource));

        return [
            'uuid' => $this->uuid,
            'stripe_txn_id' => $this->stripe_txn_id,
            'store_id' => $this->store_id,
            'currency' => $this->store->country->currency_code ?? 'CAD',
            'payout_date' => $this->payout_date,
            'status' => $this->status,
            'amount' => $all->sum('payoutAmount') / 100,
            'details' => $this->whenLoaded('details', function ($details) use ($all, $filter) {
                $items = $details instanceof Paginator
                    ? $details->getCollection()
                    : $details;

                return $items
                    ->when(
                        ! $details instanceof Paginator && $filter['status'] !== 'all',
                        function ($collection) use ($filter) {
                            return $collection->filter(function ($detail) use ($filter) {
                                return $detail->order?->payment_status === $filter['status'];
                            });
                        },
                        fn ($collection) => $collection
                    )
                    ->map(fn ($detail) => (new PayoutDetailsResource($detail))->setCalculatedData($all))
                    ->values();
            }),
            'summary' => $this->whenLoaded('details', function () use ($all) {
                return [
                    'subtotal' => $all->sum('total') / 100,
                    'dogpack_fee' => $all->sum('commission') / 100,
                    'dp_contribution' => $all->sum('display_dp_contribution') / 100,
                    'shipping' => $all->sum('shipping') / 100,
                    'stripe' => $all->sum('stripe') / 100,
                    'taxes' => $all->sum('tax') / 100,
                    'total' => $all->sum('payoutAmount') / 100,
                    'refunded' => $all->sum('refunded') / 100,
                ];
            }),
        ];
    }
}
