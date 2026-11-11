<?php

namespace App\Mail\Stripe;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StripeMoreDetailsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $store_name;

    public $stripe_url;

    public function __construct($store_name, $stripe_url)
    {
        $this->onQueue(config('queue.low'));
        $this->store_name = $store_name;
        $this->stripe_url = $stripe_url;
    }

    public function build()
    {
        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.stripe-more-details')
            ->subject('More details are required for your Stripe integration.');
    }
}
