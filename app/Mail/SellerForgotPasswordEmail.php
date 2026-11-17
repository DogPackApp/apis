<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerForgotPasswordEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $token,
        public string $email,
        public string $first_name,
    ) {
        $this->onQueue(config('queue.low'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.marketplace_from.address'),
                config('mail.marketplace_from.name'),
            ),
            subject: 'Reset your password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seller.forgot-password',
            with: [
                'resetUrl' => config('services.marketplace_fe_url').'seller/password/reset/'.$this->token.'?email='.urlencode($this->email),
                'name' => $this->first_name,
            ],
        );
    }
}
