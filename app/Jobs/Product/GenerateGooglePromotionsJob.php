<?php

namespace App\Jobs\Product;

use App\Services\Merchant\GoogleMerchantPromotionService;
use App\Services\Merchant\MarketplaceFeedConfig;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateGooglePromotionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $countryCode;

    public function __construct(string $countryCode = 'CA')
    {
        $this->countryCode = strtoupper($countryCode);
        $this->onQueue(config('queue.low'));
    }

    public function handle(GoogleMerchantPromotionService $service): void
    {
        try {
            $countryId = MarketplaceFeedConfig::COUNTRY_IDS[$this->countryCode] ?? null;

            if (! $countryId) {
                Log::error("GooglePromotionsJob: Invalid country code: {$this->countryCode}");

                return;
            }

            $result = $service->generate($countryId);

            if ($result['success']) {
                Log::info("GooglePromotionsJob [{$this->countryCode}]: {$result['message']}");
            } else {
                Log::warning("GooglePromotionsJob [{$this->countryCode}]: {$result['message']}");
            }
        } catch (Exception $e) {
            Log::error("GooglePromotionsJob [{$this->countryCode}]: ".$e->getMessage());
        }
    }
}
