<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class DisputeReceived extends Notification
{
    use Queueable;

    public function __construct(public array $payload) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $title = 'Dispute received';
        if (config('app.env') !== 'Production') {
            $title = "🔵 {$title} 🔵";
        } else {
            $title = "🚨 {$title} 🚨";
        }

        return (new SlackMessage)
            ->warning()
            ->content($title)
            ->attachment(function ($attachment) {
                $attachment
                    ->title('Stripe dispute opened')
                    ->fields([
                        'Dispute' => (string) ($this->payload['dispute_id'] ?? 'N/A'),
                        'Reason' => (string) ($this->payload['reason'] ?? 'N/A'),
                        'Status' => (string) ($this->payload['status'] ?? 'N/A'),
                        'Amount' => (string) ($this->payload['amount'] ?? 'N/A'),
                        'Currency' => (string) ($this->payload['currency'] ?? 'N/A'),
                        'Payment Intent' => (string) ($this->payload['payment_intent_id'] ?? 'N/A'),
                        'Orders' => (string) ($this->payload['orders'] ?? 'Order not found'),
                    ])
                    ->footer(config('app.name'))
                    ->timestamp(now());
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
