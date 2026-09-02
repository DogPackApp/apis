<?php

namespace App\Mail;

use App\Models\Seller\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerPasswordUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Seller $seller)
    {
        $this->onQueue(config('queue.low'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.marketplace_from.address'),
                config('mail.marketplace_from.name'),
            ),
            subject: 'Your password has been changed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seller.password-updated',
            with: [
                'name' => $this->seller->first_name,
            ],
        );
    }
}
