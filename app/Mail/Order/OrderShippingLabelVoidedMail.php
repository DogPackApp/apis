<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderShippingLabelVoidedMail extends Mailable implements ShouldBeUnique, ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;

    public $orderId;

    public $orders;

    public $summary = [];

    public $order;

    public function __construct($user, $orderId)
    {
        $this->onQueue(config('queue.low'));
        $this->user = $user;
        $this->orderId = $orderId;
    }

    public function build()
    {
        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.shipping-label-voided')
            ->subject('Shipping label voided');
    }
}
