<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Throwable;

class ReindexProductsNotification extends Notification
{
    use Queueable;

    /**
     * @param  array{indexed?: int, failed?: int, index?: string}|null  $result
     */
    public function __construct(
        public bool $success,
        public ?array $result = null,
        public ?int $pruned = null,
        public ?Throwable $exception = null,
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
        if ($this->success) {
            $title = '✅ Products Reindex Completed ✅';

            if (config('app.env') !== 'Production') {
                $title = '🟢 Products Reindex Completed 🟢';
            }

            return (new SlackMessage)
                ->success()
                ->content($title)
                ->attachment(function ($attachment) {
                    $attachment
                        ->title('Reindex Details')
                        ->fields([
                            'Index' => $this->result['index'] ?? 'N/A',
                            'Indexed' => (string) ($this->result['indexed'] ?? 0),
                            'Failed' => (string) ($this->result['failed'] ?? 0),
                            'Pruned' => (string) ($this->pruned ?? 0),
                            'Timestamp' => ($this->timestamp ?? now('America/Toronto')->format('Y-m-d H:i:s')).' (America/Toronto)',
                        ])
                        ->footer(config('app.name'))
                        ->timestamp(now());
                });
        }

        $title = '🚨 Products Reindex Failed 🚨';

        if (config('app.env') !== 'Production') {
            $title = '🟡 Products Reindex Failed 🟡';
        }

        return (new SlackMessage)
            ->error()
            ->content($title)
            ->attachment(function ($attachment) {
                $attachment
                    ->title('Error Details')
                    ->fields([
                        'Timestamp' => ($this->timestamp ?? now('America/Toronto')->format('Y-m-d H:i:s')).' (America/Toronto)',
                        'Reason' => $this->exception?->getMessage() ?? 'N/A',
                        'File' => $this->exception?->getFile() ?? 'N/A',
                        'Line' => (string) ($this->exception?->getLine() ?? 'N/A'),
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
