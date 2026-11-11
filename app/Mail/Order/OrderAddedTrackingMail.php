<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderAddedTrackingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;

    public $order;

    public $tracking;

    public $trackingUrl;

    public $carrier;

    public function __construct($user, $order, $tracking, $carrier, $trackingUrl = null)
    {
        $this->onQueue(config('queue.low'));
        $this->user = $user;
        $this->order = $order;
        $this->tracking = $tracking;
        $this->trackingUrl = $trackingUrl;
        $this->carrier = $carrier ?? 'N/A';
    }

    public function build()
    {
        $this->order = $this->order->load(['customer', 'store.address', 'items.product']);

        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.tracking-added')
            ->subject('Your order is on its way!');
    }
}
