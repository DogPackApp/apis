<?php

namespace App\Console\Commands;

use App\Services\AuthFailureNotifier;
use Illuminate\Console\Command;

class SellerTestSlackAlert extends Command
{
    protected $signature = 'seller:test-slack-alert {email=test@example.com}';

    protected $description = 'Send a test SellerAuthFailed Slack alert to verify the webhook configuration';

    public function handle(AuthFailureNotifier $notifier): int
    {
        if (! config('services.slack.seller_auth_webhook')) {
            $this->error('SLACK_SELLER_AUTH_WEBHOOK is not set — configure it in .env before testing.');

            return self::FAILURE;
        }

        $notifier->notify(
            'test',
            (string) $this->argument('email'),
            new \RuntimeException('This is a test alert triggered by php artisan seller:test-slack-alert.'),
        );

        $this->info('Test Slack alert dispatched. Check the configured seller auth webhook channel.');

        return self::SUCCESS;
    }
}
