<?php

namespace App\Mail\Order;

use App\Models\Order\StoreOrder;

trait BaseMailer
{
    public function identifyCurrency($customer): void
    {
        $customerCountry = $customer->shipping_address['country'] ?? 'CAD';
        $this->currency = $this->countryService->getByShortCode(strtoupper($customerCountry))['currency_symbol'] ?? 'CAD';
    }

    public function identifyStoreCurrency($country): void
    {
        $sellerCurrency = $country?->currency_code ?? 'CAD';
        $this->currency = $this->countryService->getByCurrencyCode(strtoupper($sellerCurrency))['currency_symbol'] ?? 'CAD';
    }

    protected function delivery($order, $rate): array
    {
        $this->delivery = [
            'type' => $order->shipping_method ?? StoreOrder::DELIVERY_FLAT,
            'name' => 'Standard Shipping',
            'description' => '3-4 days',
            'rate' => 'NA',
        ];

        if ($order->shipping_method === 'WIP') {
            return $this->delivery;
        }

        if ($order->shipping_method === StoreOrder::DIGITAL_DELIVERY) {
            $delivery['type'] = StoreOrder::DIGITAL_DELIVERY;
            $delivery['name'] = 'Digital Delivery:';
            $delivery['description'] = 'Product delivered digitally by seller';
            $delivery['rate'] = null;
        } elseif ($order->shipping_method === StoreOrder::DELIVERY_EASYPOST) {
            $delivery['type'] = StoreOrder::DELIVERY_EASYPOST;
            $delivery['name'] = $order->shipping['carrier'] ?? 'NA';
            $delivery['description'] = $order->shipping['service'] ?? 'NA';
            $delivery['rate'] = $rate;
        } else {
            $delivery['type'] = StoreOrder::DELIVERY_FLAT;
            $delivery['name'] = $order->shipping['shipping_rate_name'] ?? 'NA';
            $delivery['description'] = $order->shipping['shipping_rate_description'] ?? 'NA';
            $delivery['rate'] = $rate;
        }

        return $delivery;
    }
}
