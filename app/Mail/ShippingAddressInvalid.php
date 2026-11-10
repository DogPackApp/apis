<?php

namespace App\Mail;

use App\Models\Store\ShippingAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ShippingAddressInvalid extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ?ShippingAddress $shipping;

    public function __construct(?ShippingAddress $shipping = null)
    {
        $this->onQueue(config('queue.low'));
        $this->shipping = $shipping;
    }

    public function build(): self
    {
        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->subject("[URGENT] Easypost Shipping Address Invalid for {$this->shipping->store->name}")
            ->view('emails.shipping-address-invalid', [
                'shipping' => $this->shipping,
            ]);
    }
}
