<?php

namespace App\Notifications;

use Illuminate\Notifications\Notifiable;

class SlackNotifier
{
    use Notifiable;

    public function routeNotificationForSlack(): string
    {
        return config('services.slack.webhook_url');
    }
}
