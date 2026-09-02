<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerOTPMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public mixed $user, public mixed $otp) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                config('mail.marketplace_from.address'),
                config('mail.marketplace_from.name'),
            ),
            subject: 'Your OTP Verification Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seller.otp',
        );
    }
}
