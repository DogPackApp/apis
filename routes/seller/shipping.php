<?php

use App\Http\Controllers\Seller\Shipping\CustomShippingMethodController;
use App\Http\Controllers\Seller\Shipping\CustomShippingMethodUpdateController;
use App\Http\Controllers\Seller\Shipping\PackagingShowController;
use App\Http\Controllers\Seller\Shipping\ShippingAutocompleteController;
use App\Http\Controllers\Seller\Shipping\ShippingPackagingController;
use App\Http\Controllers\Seller\Shipping\ShippingPlacesController;
use App\Http\Controllers\Seller\Shipping\ShippingRatesShowController;
use App\Http\Controllers\Seller\Shipping\ShippingShowController;
use Illuminate\Support\Facades\Route;

Route::prefix('shipping')->group(function () {
    Route::get('/rates', ShippingRatesShowController::class)->name('shipping.rates.show');
    Route::post('/', ShippingPackagingController::class)->name('shipping.packaging.create');
    Route::post('/custom', CustomShippingMethodController::class)->name('custom.shipping.method.create');
    Route::put('/custom/{shippingRateUuid}', CustomShippingMethodUpdateController::class)->name('custom.shipping.method.update');
    Route::get('/shipping', ShippingShowController::class)->name('shipping.show');
    Route::get('/packaging', PackagingShowController::class)->name('packaging.show');
    Route::post('/address/autocomplete', ShippingAutocompleteController::class)->name('shipping.address.autocomplete');
    Route::post('/address/places', ShippingPlacesController::class)->name('shipping.places.autocomplete');
});
