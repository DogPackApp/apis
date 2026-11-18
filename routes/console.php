<?php

use App\Jobs\Product\GenerateGoogleFeedJob;
use App\Jobs\Product\GenerateGooglePromotionsJob;
use App\Jobs\Product\ReindexProductsJob;
use App\Services\Merchant\MarketplaceFeedConfig;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('services.cron_server') === 'marketplace') {
    Schedule::job(new ReindexProductsJob)->dailyAt('00:03')->timezone('America/Toronto');

    foreach (array_keys(MarketplaceFeedConfig::COUNTRY_IDS) as $countryCode) {
        Schedule::job(new GenerateGoogleFeedJob($countryCode))->daily();
        Schedule::job(new GenerateGooglePromotionsJob($countryCode))->dailyAt('01:00');
    }
}
