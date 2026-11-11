<?php

use App\Http\Controllers\Stripe\StripeInvoicePdfController;
use App\Http\Controllers\Subscribe\PlansListController;
use App\Http\Controllers\Subscribe\StripeAddCardController;
use App\Http\Controllers\Subscribe\StripeApproximateAmountDifference;
use App\Http\Controllers\Subscribe\StripeCalculateNextBillingController;
use App\Http\Controllers\Subscribe\StripeChangeSubscriptionPlanController;
use App\Http\Controllers\Subscribe\StripeClearController;
use App\Http\Controllers\Subscribe\StripeCurrentPlanController;
use App\Http\Controllers\Subscribe\StripeDeleteCardController;
use App\Http\Controllers\Subscribe\StripeestimateNewPlanCostController;
use App\Http\Controllers\Subscribe\StripePricingController;
use App\Http\Controllers\Subscribe\StripeRetryPaymentController;
use App\Http\Controllers\Subscribe\StripeStoreCardController;
use App\Http\Controllers\Subscribe\StripeSubscribeListController;
use App\Http\Controllers\Subscribe\StripeUpdateDefaultCardController;
use App\Http\Controllers\Subscribe\SubscribeStripeController;
use Illuminate\Support\Facades\Route;

Route::prefix('subscribe')->group(function () {
    Route::post('/list', StripeSubscribeListController::class)->name('subscribe.list');
    Route::get('/pricings', StripePricingController::class)->name('subscribe.createPlan.pricings');
    Route::get('/currentPlan', StripeCurrentPlanController::class)->name('subscribe.currentPlan');
    Route::post('/plan', SubscribeStripeController::class)->name('subscribe.createPlan');
    Route::post('/getCards', StripeStoreCardController::class)->name('subscribe.getCards');
    Route::post('/addCard', StripeAddCardController::class)->name('subscribe.addCard');
    Route::post('/updateDefault', StripeUpdateDefaultCardController::class)->name('subscribe.updateDefault');
    Route::post('/deleteCard', StripeDeleteCardController::class)->name('subscribe.deleteCard');
    Route::post('/changeSubscriptionPlan', StripeChangeSubscriptionPlanController::class)->name('subscribe.changeSubscriptionPlan');
    Route::post('/clearSubscription', StripeClearController::class)->name('subscribe.clearSubscription');
    Route::get('/calculateNextBilling', StripeCalculateNextBillingController::class)->name('subscribe.calculateNextBilling');
    Route::post('/approximateAmountDifference', StripeApproximateAmountDifference::class)->name('subscribe.approximateAmountDifference');
    Route::post('/estimateNewPlanCost', StripeestimateNewPlanCostController::class)->name('subscribe.estimateNewPlanCost');
    Route::get('/plans', PlansListController::class)->name('subscribe.plans.list');
    Route::put('/retryPayment', StripeRetryPaymentController::class)->name('subscribe.retryPayment');
    Route::get('/invoicePdf', StripeInvoicePdfController::class)->name('subscribe.invoicePdf');
});
