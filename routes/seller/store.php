<?php

use App\Http\Controllers\Seller\SellerChangePasswordController;
use App\Http\Controllers\Seller\SellerCurrentPasswordController;
use App\Http\Controllers\Seller\SellerTwoFactorDisableController;
use App\Http\Controllers\Seller\SellerTwoFactorEnableController;
use App\Http\Controllers\Seller\SellerTwoFactorOtpController;
use App\Http\Controllers\Seller\StoreAddressShowController;
use App\Http\Controllers\Seller\StoreCreateController;
use App\Http\Controllers\Seller\StoreShowController;
use App\Http\Controllers\Seller\StoreStripeConnectController;
use App\Http\Controllers\Seller\SubscribeSaveAllowedPercentsController;
use Illuminate\Support\Facades\Route;

Route::get('/store/subscribe-save/allowed-percents', SubscribeSaveAllowedPercentsController::class)
    ->name('seller.store.subscribe-save.allowed-percents');
Route::get('/store/address', StoreAddressShowController::class)->name('seller.store.address.show');
Route::get('/store', StoreShowController::class)->name('seller.store.show');
Route::post('/store', StoreCreateController::class)->name('seller.store.create');
Route::post('/stripe/connect', StoreStripeConnectController::class)->name('seller.store.stripe.connect');

Route::prefix('store/settings/security')->group(function () {
    Route::post('/current-password', SellerCurrentPasswordController::class)->name('seller.password.current');
    Route::post('/new-password', SellerChangePasswordController::class)->name('seller.password.change');
    Route::post('/2fa-verification', SellerTwoFactorOtpController::class)->name('seller.2fa.otp');
    Route::post('/2fa-verification/enable', SellerTwoFactorEnableController::class)->name('seller.2fa.enable');
    Route::post('/2fa-verification/disable', SellerTwoFactorDisableController::class)->name('seller.2fa.disable');
});
