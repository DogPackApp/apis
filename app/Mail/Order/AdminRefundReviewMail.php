<?php

namespace App\Mail\Order;

use App\Mail\Marketplace\BaseMailer;
use App\Services\Order\CountryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class AdminRefundReviewMail extends Mailable implements ShouldQueue
{
    use BaseMailer, Queueable;

    public $customerName;

    public $customerEmail;

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

    public bool $isCancellation;

    public function __construct(
        $user,
        $order,
        $amount,
        $itemsAmount = null,
        $taxAmount = null,
        $deliveryAmount = null,
        $reason = null,
        bool $isCancellation = false,
    ) {
        $this->onQueue(config('queue.low'));
        $this->user = $user;
        $this->customerName = $user?->name;
        $this->customerEmail = $user?->email;
        $this->order = $order;
        $this->amount = $amount ?? 'N/A';
        $this->itemsAmount = $itemsAmount;
        $this->taxAmount = $taxAmount;
        $this->deliveryAmount = $deliveryAmount;
        $this->reason = $reason;
        $this->isCancellation = $isCancellation;
        $this->countryService = app(CountryService::class);
    }

    public function build()
    {
        if ($this->user && ! empty($this->user->shipping_address)) {
            $this->identifyCurrency($this->user);
        }

        $this->isUk = $this->user
            ? $this->countryService->matchCountryCode($this->user['country'] ?? null, CountryService::COUNTRY_CODES['UK'])
            : false;
        $this->order->load('store');

        $subject = $this->isCancellation
            ? "Review cancellation for order #{$this->order->order_no}"
            : "Review partial refund for order #{$this->order->order_no}";

        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.admin-refund-review')
            ->subject($subject);
    }
}
