<?php

namespace App\Jobs\Product;

use App\Services\Merchant\GoogleMerchantFeedService;
use App\Services\Merchant\MarketplaceFeedConfig;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateGoogleFeedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $countryCode;

    public function __construct(string $countryCode = 'CA')
    {
        $this->countryCode = strtoupper($countryCode);
        $this->onQueue('mkt-low');
    }

    public function handle(GoogleMerchantFeedService $service): void
    {
        try {
            $countryId = MarketplaceFeedConfig::COUNTRY_IDS[$this->countryCode] ?? null;

            if (! $countryId) {
                Log::error("GoogleFeedJob: Invalid country code: {$this->countryCode}");

                return;
            }

            $result = $service->generateGoogleAndAwin($countryId, 0);

            if ($result['google']['success']) {
                Log::info("GoogleFeedJob [{$this->countryCode}]: {$result['google']['message']}");
            } else {
                Log::warning("GoogleFeedJob [{$this->countryCode}]: {$result['google']['message']}");
            }

            if ($result['awin']['success']) {
                Log::info("AwinFeedJob [{$this->countryCode}]: {$result['awin']['message']}");
            } else {
                Log::warning("AwinFeedJob [{$this->countryCode}]: {$result['awin']['message']}");
            }
        } catch (Exception $e) {
            Log::error("GoogleFeedJob [{$this->countryCode}]: ".$e->getMessage());
        }
    }
}
