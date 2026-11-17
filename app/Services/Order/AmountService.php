<?php

namespace App\Services\Order;

class AmountService
{
    public $storeOrder;

    public function __construct($order)
    {
        $this->storeOrder = $order;
    }

    public function orderTotal(): array
    {
        $billing = $this->storeOrder->orderBilling;
        $buyerWalletDiscount = is_array($billing->wallet)
            ? ($billing->wallet['discount_amount'] ?? null)
            : null;

        return [
            'subtotal' => round(($billing->currency_details['subtotal'] ?? 0), 2),
            'delivery' => round(($billing->currency_details['delivery'] ?? 0), 2),
            'discount' => round(($billing->currency_details['discount'] ?? 0), 2),
            'wallet_discount' => round((float) ($buyerWalletDiscount ?? (((int) ($billing->wallet_discount ?? 0)) / 100)), 2),
            'total' => round(($billing->currency_details['total'] ?? 0), 2),
            'tax' => round(($billing->currency_details['tax'] ?? 0), 2),
        ];
    }

    public function sellerTotal(): array
    {
        $billing = $this->storeOrder->orderBilling;

        $delivery = round(((int) ($billing->shipping_rate ?? 0)) / 100, 2);

        $discount = (int) ($billing->discount ?? 0);
        $walletDiscount = $billing->sellerWalletDiscountCents();

        return [
            'subtotal' => round(($billing->subtotal ?? 0), 2),
            'delivery' => round(($delivery ?? 0), 2),
            'discount' => $discount,
            'display_discount' => $discount + $walletDiscount,
            'dogpack_contribution' => round(($billing->dp_contribution ?? 0), 2),
            'seller_contribution' => round(($billing->seller_contribution ?? 0), 2),
            'wallet_discount' => $walletDiscount,
            'wallet' => $billing->wallet,
            'total' => round($billing->sellerOrderTotalCents(), 2),
            'tax' => round(($billing->tax ?? 0), 2),
        ];
    }
}
