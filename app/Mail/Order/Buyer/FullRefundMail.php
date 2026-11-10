<?php

namespace App\Mail\Order\Buyer;

use App\Services\Order\CountryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class FullRefundMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public $customer_name;

    public $user;

    public $order;

    public $amount;

    public $itemsAmount;

    public $taxAmount;

    public $deliveryAmount;

    public $reason;

    public $countryService;

    public $currency;

    public bool $isUk = false;

    public bool $hideCatalogPrices = false;

    public function __construct($user, $order, $amount, $itemsAmount = null, $taxAmount = null, $deliveryAmount = null, $reason = null, $currency = null)
    {
        $this->onQueue(config('queue.low'));
        $this->customer_name = $user?->name;
        $this->user = $user;
        $this->order = $order;
        $this->amount = $amount ?? 'N/A';
        $this->itemsAmount = $itemsAmount;
        $this->taxAmount = $taxAmount;
        $this->deliveryAmount = $deliveryAmount;
        $this->reason = $reason;
        $this->countryService = app(CountryService::class);
        $this->currency = $currency;
    }

    public function build()
    {
        $this->isUk = $this->countryService->matchCountryCode($this->user['country'] ?? null, CountryService::COUNTRY_CODES['UK']);

        $this->order->load('store');

        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.refunded')
            ->subject('Your order is fully refunded!');
    }
}
