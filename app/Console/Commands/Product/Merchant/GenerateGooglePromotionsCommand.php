<?php

namespace App\Console\Commands\Product\Merchant;

use App\Services\Merchant\GoogleMerchantPromotionService;
use App\Services\Merchant\MarketplaceFeedConfig;
use Illuminate\Console\Command;
use Throwable;

class GenerateGooglePromotionsCommand extends Command
{
    protected $signature = 'marketplace:generate-google-promotions
        {--country=CA : Country code (CA, US, or GB)}';

    protected $description = 'Generate Google Merchant Center Subscribe & Save promotions XML and upload to S3 (kept in storage/app/temp on docker-local)';

    public function handle(GoogleMerchantPromotionService $service): int
    {
        $countryCode = strtoupper($this->option('country'));

        if (! isset(MarketplaceFeedConfig::COUNTRY_IDS[$countryCode])) {
            $this->error("Invalid country code: {$countryCode}. Use CA, US, or GB.");

            return self::FAILURE;
        }

        $this->info("Generating Google Merchant Subscribe & Save promotions for {$countryCode}...");

        try {
            $result = $service->generate(MarketplaceFeedConfig::COUNTRY_IDS[$countryCode]);
        } catch (Throwable $e) {
            $this->error("Promotions generation failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $result['success']) {
            $this->warn($result['message']);

            return self::FAILURE;
        }

        $this->info($result['message']);
        if (isset($result['url'])) {
            $this->info("Feed URL: {$result['url']}");
        }
        if (isset($result['path'])) {
            $this->info("File: {$result['path']}");
        }

        return self::SUCCESS;
    }
}
