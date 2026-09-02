<?php

namespace App\Services;

use App\Notifications\Seller\SellerAuthFailed;
use Illuminate\Support\Facades\Notification;

class AuthFailureNotifier
{
    public function notify(string $event, string $email, \Throwable $exception): void
    {
        Notification::route('slack', config('services.slack.seller_auth_webhook'))
            ->notify(new SellerAuthFailed($event, $email, $exception));
    }
}
