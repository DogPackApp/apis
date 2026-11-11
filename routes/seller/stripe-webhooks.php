<?php

use App\Http\Controllers\Stripe\StripePaymentCaptureWebhookController;
use App\Http\Controllers\Stripe\StripeWebhookController;
use App\Http\Controllers\Stripe\SubscribeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('stripe')->group(function () {
    Route::post('/webhook', StripeWebhookController::class)->name('stripe.webhook');
    Route::post('/webhook/subscription', SubscribeWebhookController::class)->name('stripe.webhook.subscription');
    Route::post('/webhook/paymentcapture', StripePaymentCaptureWebhookController::class)->name('stripe.webhook.paymentcapture');
});
