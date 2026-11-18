<?php

namespace App\Console\Commands\Product\Merchant;

use App\Services\Merchant\GoogleMerchantFeedService;
use App\Services\Merchant\MarketplaceFeedConfig;
use Illuminate\Console\Command;
use Throwable;

class GenerateGoogleFeedCommand extends Command
{
    protected $signature = 'marketplace:generate-google-feed
        {--country=CA : Country code (CA, US, or GB)}
        {--limit=0 : Maximum number of products (0 = all)}
        {--local : Save the XML to storage/app/feeds locally and skip the S3 upload}';

    protected $description = 'Generate Google Merchant Center XML product feed and upload to S3';

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

        $this->info("Generating Google Merchant feed for {$countryCode} ({$currency})"
            .($limit > 0 ? " (limit: {$limit})" : ' (all products)').'...');

        try {
            $result = $service->generate($countryId, $limit, (bool) $this->option('local'));
        } catch (Throwable $e) {
            $this->error("Feed generation failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $result['success']) {
            $this->warn($result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);
        $this->info(($this->option('local') ? 'Local file: ' : 'Feed URL: ').$result['url']);

        return self::SUCCESS;
    }
}
