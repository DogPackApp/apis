<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class PayoutDetailsResource extends JsonResource
{
    protected Collection $calculatedData;

    public function setCalculatedData(Collection $data): static
    {
        $this->calculatedData = $data;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $orderValues = $this->collection
            ? $this->calculatedData->first(fn ($row) => ($row['detailUuid'] ?? null) === $this->uuid)
            : $this->calculatedData->firstWhere('orderUuid', $this->order->uuid);
        $orderValues = is_array($orderValues) ? $orderValues : [];

        $payoutAmount = ($orderValues['payoutAmount'] ?? 0) / 100;

        if (! empty($orderValues['collection'])) {
            return [
                'uuid' => $this->uuid,
                'order' => $this->order->order_no,
                'order_uuid' => $orderValues['orderUuid'] ?? null,
                'order_date' => $this->order->created_at,
                'payment_status' => $this->order->payment_status,
                'fulfilment_status' => $this->order->fulfilment_status,
                'payout' => $payoutAmount,
                'total' => null,
                'dogpack_fee' => null,
                'refunded' => null,
                'shipping' => null,
                'stripe' => null,
                'taxes' => null,
                'dp_contribution' => 0,
                'promo' => null,
                'wallet' => null,
            ];
        }

        $billing = $this->order->orderBilling;
        $dpContributionCents = (int) ($billing?->dp_contribution ?? 0);
        $sellerContributionCents = (int) ($billing?->seller_contribution ?? 0);
        $billingPromo = $billing?->promo;
        $hasPromo = ! empty($billingPromo);
        $billingWallet = $billing?->wallet;
        $hasWallet = ! empty($billingWallet);
        $walletDiscountCents = $billing ? $billing->sellerWalletDiscountCents() : 0;
        $commissionCents = (int) ($orderValues['commission'] ?? 0);

        return [
            'uuid' => $this->uuid,
            'order' => $this->order->order_no,
            'order_uuid' => $orderValues['orderUuid'] ?? null,
            'order_date' => $this->order->created_at,
            'payment_status' => $this->order->payment_status,
            'fulfilment_status' => $this->order->fulfilment_status,
            'payout' => $payoutAmount,
            'total' => $orderValues['total'] / 100,
            'dogpack_fee' => $orderValues['commission'] ? $orderValues['commission'] / 100 : 'NA',
            'refunded' => $orderValues['refunded'] > 0 ? $orderValues['refunded'] / 100 : 'NA',
            'shipping' => $orderValues['shipping'] > 0 ? $orderValues['shipping'] / 100 : 'NA',
            'stripe' => $this->order->orderBilling->stripe_fee / 100,
            'taxes' => ($orderValues['tax'] ?? 0) / 100,
            'dp_contribution' => $billing ? $billing->displayDpContributionCents() / 100 : 0,
            'promo' => $hasPromo ? array_merge($billingPromo, [
                'dogpack_contribution' => $dpContributionCents / 100,
                'seller_contribution' => $sellerContributionCents / 100,
            ]) : null,
            'wallet' => $hasWallet ? array_merge($billingWallet, [
                'discount_amount' => $walletDiscountCents / 100,
            ]) : null,
        ];
    }
}
