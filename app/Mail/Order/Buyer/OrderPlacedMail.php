<?php

namespace App\Mail\Order\Buyer;

use App\Mail\Order\BaseMailer;
use App\Models\Order\StoreOrder;
use App\Services\Order\AmountService;
use App\Services\Order\CountryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderPlacedMail extends Mailable implements ShouldQueue
{
    use BaseMailer, Queueable, SerializesModels;

    public array $user;

    public $orderId;

    public $orders;

    public $summary = [];

    public array $delivery = [];

    public array $deliveryPerOrder = [];

    public $currency;

    public $countryService;

    public bool $isUk = false;

    public bool $isReplacement = false;

    public bool $isRenewal = false;

    public function __construct($user, $orderId)
    {
        $this->onQueue(config('queue.low'));
        $this->countryService = app(CountryService::class);
        $this->user = $user;
        $this->orderId = $orderId;
        $this->delivery = [];
        $this->deliveryPerOrder = [];
    }

    public function build()
    {
        $this->orders = StoreOrder::on('mysql_reader')
            ->withoutGlobalScope('admin_status_default')
            ->with(['store', 'customer', 'items', 'orderBilling', 'paymentDetail'])
            ->where('order_no', $this->orderId)
            ->get();

        $summary = [
            'subtotal' => 0,
            'delivery' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
        ];

        foreach ($this->orders as $order) {

            $totals = (new AmountService($order))->orderTotal();

            $this->identifyCurrency($order->customer);

            $summary['subtotal'] += $totals['subtotal'];
            $summary['delivery'] += $totals['delivery'];
            $summary['tax'] += $totals['tax'];
            $summary['discount'] += $totals['discount'];
            $summary['total'] += $totals['total'];

            // Store delivery info per order
            $this->deliveryPerOrder[$order->id] = $this->delivery($order, $totals['delivery']);

            // Keeping this for now
            $this->delivery = $this->deliveryPerOrder[$order->id];
        }

        $this->summary = $summary;

        $this->isUk = $this->countryService->matchCountryCode($this->user['country'] ?? null, CountryService::COUNTRY_CODES['UK']);
        $this->isReplacement = $this->orders->contains(
            fn ($order) => $order->paymentDetail?->payment_method === StoreOrder::ORDER_PM_SUPERADMIN
        );

        // Only a Subscribe & Save renewal sets subscription_id on the store order; the first order links it on the item.
        $this->isRenewal = ! $this->isReplacement && $this->orders->contains(fn ($order) => $order->subscription_id !== null);

        $orderNo = $this->orders->first()->order_no;
        $subject = match (true) {
            $this->isReplacement => "Replacement order #{$orderNo} confirmed",
            $this->isRenewal => "Order #{$orderNo} - Your Subscribe & Save Renewal",
            default => "Order #{$orderNo} confirmed",
        };

        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.order-confirmation-buyer')
            ->subject($subject);
    }
}
