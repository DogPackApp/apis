<?php

namespace App\Mail\Order;

use App\Mail\Marketplace\BaseMailer;
use App\Models\Order\StoreOrder;
use App\Services\Order\AmountService;
use App\Services\Order\CountryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderReceivedMail extends Mailable implements ShouldQueue
{
    use BaseMailer, Queueable, SerializesModels;

    public $user;

    public $orderUuid;

    public $orderId;

    public $order;

    public $summary = [];

    public $delivery = [];

    public $currency;

    public $countryService;

    public $customer;

    public bool $isUk = false;

    public function __construct($user, $orderUuid, $customer)
    {
        $this->onQueue(config('queue.low'));
        $this->user = $user;
        $this->orderUuid = $orderUuid;
        $this->customer = $customer;
        $this->countryService = app(CountryService::class);
    }

    public function build()
    {
        $this->order = StoreOrder::query()
            ->withoutGlobalScope('admin_status_default')
            ->with(['store.country', 'customer', 'items', 'orderBilling', 'paymentDetail'])
            ->where('uuid', $this->orderUuid)
            ->first();

        $this->orderId = $this->order->order_no;

        $summary = [
            'subtotal' => 0,
            'delivery' => 0,
            'tax' => 0,
            'discount' => 0,
            'display_discount' => 0,
            'wallet_discount' => 0,
            'dogpack_contribution' => 0,
            'total' => 0,
        ];

        $totals = (new AmountService($this->order))->sellerTotal();

        $summary['subtotal'] += $totals['subtotal'];
        $summary['delivery'] += $totals['delivery'];
        $summary['tax'] += $totals['tax'];
        $summary['discount'] += $totals['discount'];
        $summary['display_discount'] += $totals['display_discount'];
        $summary['wallet_discount'] += $totals['wallet_discount'];
        $summary['dogpack_contribution'] += $totals['dogpack_contribution'];
        $summary['total'] += $totals['total'];

        $this->identifyStoreCurrency($this->order->store->country);

        $this->delivery = $this->delivery($this->order, $summary['delivery']);

        $this->summary = $summary;

        $this->isUk = $this->countryService->matchCountryCode($this->customer['country'] ?? null, CountryService::COUNTRY_CODES['UK']);

        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.order-confirmation-seller')
            ->subject("#{$this->orderId} - You got a new order from the DogPack Marketplace!");
    }
}
