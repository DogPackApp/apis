<?php

namespace App\Console\Commands\Product\Merchant;

use App\Services\Merchant\GoogleMerchantFeedService;
use App\Services\Merchant\MarketplaceFeedConfig;
use Illuminate\Console\Command;
use Throwable;

class GenerateAwinFeedCommand extends Command
{
    protected $signature = 'marketplace:generate-awin-feed
        {--country=CA : Country code (CA, US, or GB)}
        {--limit=0 : Maximum number of products (0 = all)}';

    protected $description = 'Generate Awin CSV product feed and upload to S3';

    public function handle(GoogleMerchantFeedService $service): int
    {
        $countryCode = strtoupper($this->option('country'));
        $limit = (int) $this->option('limit');

        if (! isset(MarketplaceFeedConfig::COUNTRY_IDS[$countryCode])) {
            $this->error("Invalid country code: {$countryCode}. Use CA, US, or GB.");

            return self::FAILURE;
        }

        $countryId = MarketplaceFeedConfig::COUNTRY_IDS[$countryCode];
        $currency = MarketplaceFeedConfig::CURRENCIES[$countryId];

        $this->info("Generating Awin feed for {$countryCode} ({$currency})"
            .($limit > 0 ? " (limit: {$limit})" : ' (all products)').'...');

        try {
            $result = $service->generateAwin($countryId, $limit);
        } catch (Throwable $e) {
            $this->error("Feed generation failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $result['success']) {
            $this->warn($result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);
        $this->info("Feed URL: {$result['url']}");

        return self::SUCCESS;
    }
}
