<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderManualFulfilledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;

    public $order;

    public function __construct($user, $order)
    {
        $this->onQueue(config('queue.low'));
        $this->user = $user;
        $this->order = $order;
    }

    public function build()
    {
        $this->order = $this->order->load(['customer', 'store.address', 'items.product']);

        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.manually-fullfilled')
            ->subject('Your order is being processed!');
    }
}
