<?php

namespace App\Mail\Stripe;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StripeApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $store_name;

    public function __construct($store_name)
    {
        $this->onQueue(config('queue.low'));
        $this->store_name = $store_name;
    }

    public function build()
    {
        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.stripe-approved')
            ->subject('Your Stripe setup for DogPack has been approved!');
    }
}
