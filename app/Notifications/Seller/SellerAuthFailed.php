<?php

namespace App\Notifications\Seller;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class SellerAuthFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public string $reason;

    public string $exceptionFile;

    public int $exceptionLine;

    public function __construct(
        public string $event,
        public string $email,
        \Throwable $exception,
    ) {
        $this->reason = $exception->getMessage();
        $this->exceptionFile = $exception->getFile();
        $this->exceptionLine = $exception->getLine();

        $this->onQueue(config('queue.low'));
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $prefix = app()->isProduction() ? '🚨' : '🔵';

        return (new SlackMessage)
            ->error()
            ->content("{$prefix} Seller Auth Failed: {$this->event} {$prefix}")
            ->attachment(function ($attachment): void {
                $fields = [
                    'Event' => $this->event,
                    'Email' => $this->email,
                    'Timestamp' => now()->toDateTimeString().' ('.config('app.timezone').')',
                    'Reason' => $this->reason,
                ];

                if (! app()->isProduction()) {
                    $fields['File'] = $this->exceptionFile;
                    $fields['Line'] = (string) $this->exceptionLine;
                }

                $attachment->fields($fields);
            });
    }
}
