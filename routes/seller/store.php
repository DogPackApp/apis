<?php

use App\Http\Controllers\Seller\StoreAddressShowController;
use App\Http\Controllers\Seller\StoreCreateController;
use App\Http\Controllers\Seller\StoreShowController;
use App\Http\Controllers\Seller\StoreStripeConnectController;
use App\Http\Controllers\Seller\StoreUpdateController;
use App\Http\Controllers\Seller\SubscribeSaveAllowedPercentsController;
use Illuminate\Support\Facades\Route;

Route::get('/store/subscribe-save/allowed-percents', SubscribeSaveAllowedPercentsController::class)
    ->name('seller.store.subscribe-save.allowed-percents');
Route::get('/store/address', StoreAddressShowController::class)->name('seller.store.address.show');
Route::get('/store', StoreShowController::class)->name('seller.store.show');
Route::post('/store', StoreCreateController::class)->name('seller.store.create');
Route::put('/store', StoreUpdateController::class)->name('seller.store.update');
Route::post('/stripe/connect', StoreStripeConnectController::class)->name('seller.store.stripe.connect');
