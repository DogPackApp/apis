<?php

use App\Http\Controllers\Order\EasyPost\BuyRateController;
use App\Http\Controllers\Order\EasyPost\RatesController;
use App\Http\Controllers\Order\EasyPost\VoidLabelController;
use App\Http\Controllers\Order\EasyPost\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('easypost')->group(function () {
    Route::post('/webhooks', WebhookController::class)->name('easypost.webhooks');
    Route::middleware('auth:marketplace')->post('/{store_orders:uuid}/rates', RatesController::class)->name('easypost.seller.rates');
    Route::middleware('auth:marketplace')->post('/{store_orders:uuid}/rates/buy/{rate}', BuyRateController::class)->name('easypost.seller.rates.buy');
    Route::middleware('auth:marketplace')->post('/void/{easy_posts:uuid}', VoidLabelController::class)->name('easypost.seller.label.void');
});
