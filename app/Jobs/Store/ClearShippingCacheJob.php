<?php

namespace App\Jobs\Store;

use App\Services\Cache\CacheService;
use App\Services\Store\StoreFreeShippingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Clears every cache that depends on a store's shipping zones / rates.
 */
class ClearShippingCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected int $storeId)
    {
        $this->onQueue(config('queue.medium'));
    }

    public function handle(CacheService $cacheService): void
    {
        $patterns = [
            '*'.StoreFreeShippingService::FREE_SHIPPING_CACHE_KEY_PREFIX.'*',
            '*'.StoreFreeShippingService::FREE_SHIPPING_ITEM_CACHE_KEY_PREFIX.'*',
            '*'.StoreFreeShippingService::BEST_SHIPPING_RATE_CACHE_KEY_PREFIX.$this->storeId.'*',
            '*'.StoreFreeShippingService::BEST_PRODUCT_SHIPPING_RATE_CACHE_KEY_PREFIX.$this->storeId.'*',
            '*api_stores_list_*',
            '*api_product_list_*',
            '*api_product_count_*',
        ];

        foreach ($patterns as $pattern) {
            $cacheService->forgetAllByPattern($pattern);
        }
    }
}
