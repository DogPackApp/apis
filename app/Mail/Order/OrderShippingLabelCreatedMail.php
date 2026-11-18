<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderShippingLabelCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;

    public $order;

    public $store;

    public $shipmentDetails;

    public $wasPreviousVoided;

    public function __construct($user, $order, $wasPreviousVoided)
    {
        $this->onQueue(config('queue.low'));
        $this->user = $user;
        $this->order = $order;
        $this->wasPreviousVoided = $wasPreviousVoided;
    }

    public function build()
    {
        $this->order = $this->order->load(['customer', 'store.address', 'items.product', 'easypost']);
        $this->store = $this->order->store;
        $this->shipmentDetails = $this->order->easypost->shipment;

        // if any of the shipping labels have been voided, send the new shipping label email
        if ($this->wasPreviousVoided) {
            return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
                ->view('marketplace.emails.new-shipping-label')
                ->subject('Your order is being processed!');
        }

        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.shipping-label-fulfilled')
            ->subject('Your order is being processed!');
    }
}
