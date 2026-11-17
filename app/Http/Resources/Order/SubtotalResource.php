<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubtotalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $walletDiscountCents = $this->sellerWalletDiscountCents();
        $storedDiscountCents = (int) $this->discount;
        $hasPromo = is_array($this->promo) && $this->promo !== [];
        $mainDiscountCents = $hasPromo ? 0 : $storedDiscountCents;
        $billingWallet = $this->wallet;
        $hasWallet = $walletDiscountCents > 0 || ((int) ($this->wallet_discount ?? 0)) > 0;
        $total = $this->total / 100;

        return [
            'subtotal' => $this->subtotal / 100,
            'originalSubtotal' => ($this->subtotal + $mainDiscountCents) / 100,
            'discount' => ($storedDiscountCents + $walletDiscountCents) / 100,
            'promo' => $this->promo,
            'dp_contribution' => $this->displayDpContributionCents() / 100,
            'seller_contribution' => $this->seller_contribution / 100,
            'is_wallet_redemption' => $hasWallet,
            'wallet_discount' => $walletDiscountCents / 100,
            'wallet' => $hasWallet ? array_merge($billingWallet ?? [], [
                'discount_amount' => $walletDiscountCents / 100,
            ]) : null,
            'tax_rate' => $this->tax_rate,
            'tax_breakdown' => $this->tax_breakdown,
            'tax' => $this->tax / 100,
            'shipping_rate' => $this->shipping_rate / 100,
            'total' => $this->sellerOrderTotalCents() / 100,
            'total_with_promo' => $total,
        ];
    }
}
