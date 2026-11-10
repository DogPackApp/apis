<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Throwable;

class SyncProductToIndexFailed extends Notification
{
    use Queueable;

    public function __construct(
        public int $itemId,
        public string $action,
        public Throwable $exception,
        public ?string $timestamp = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $title = '🚨 Product Index Sync Failed 🚨';

        if (config('app.env') !== 'Production') {
            $title = '🟡 Product Index Sync Failed 🟡';
        }

        return (new SlackMessage)
            ->error()
            ->content($title)
            ->attachment(function ($attachment) {
                $attachment
                    ->title('Sync Failure Details')
                    ->fields([
                        'Item ID' => (string) $this->itemId,
                        'Action' => $this->action,
                        'Timestamp' => ($this->timestamp ?? now('America/Toronto')->format('Y-m-d H:i:s')).' (America/Toronto)',
                        'Reason' => $this->exception->getMessage() ?? 'N/A',
                        'File' => $this->exception->getFile(),
                        'Line' => (string) $this->exception->getLine(),
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
        return [];
    }
}
