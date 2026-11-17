<?php

namespace App\Mail\Stripe;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentFailedSupportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build(): PaymentFailedSupportMail
    {
        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.payment-fail')
            ->subject('🚨 Payment Failed for ('.$this->data['entity_type'].') '.$this->data['entity_name'].' - '.$this->data['reason'])
            ->to(config('services.DOGPACK_EMAIL'));
    }
}
