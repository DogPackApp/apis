<?php

use App\Http\Controllers\Misc\CountryListController;
use App\Http\Controllers\Misc\CurrencyListController;
use App\Http\Controllers\Misc\LanguageListController;
use App\Http\Controllers\Misc\StateListController;
use App\Http\Controllers\Product\ProductArchiveToggleController;
use App\Http\Controllers\Product\ProductCreateController;
use App\Http\Controllers\Product\ProductListController;
use App\Http\Controllers\Product\ProductShowController;
use App\Http\Controllers\Product\ProductUpdateController;
use App\Http\Controllers\Seller\Section\SectionController;
use App\Http\Controllers\Seller\Section\SectionCreateController;
use App\Http\Controllers\Seller\Section\SectionDeleteController;
use App\Http\Controllers\Seller\Section\SectionItemAddController;
use App\Http\Controllers\Seller\Section\SectionItemDeleteController;
use App\Http\Controllers\Seller\Section\SectionItemsController;
use App\Http\Controllers\Seller\Section\SectionMoveController;
use App\Http\Controllers\Seller\Section\SectionOrderController;
use App\Http\Controllers\Seller\Section\SectionProductOrderController;
use App\Http\Controllers\Seller\Section\SectionUpdateController;
use App\Http\Controllers\Seller\SellerChangePasswordController;
use App\Http\Controllers\Seller\SellerCurrentPasswordController;
use App\Http\Controllers\Seller\SellerForgotPasswordController;
use App\Http\Controllers\Seller\SellerGoogleLoginController;
use App\Http\Controllers\Seller\SellerLoginController;
use App\Http\Controllers\Seller\SellerLoginOTPController;
use App\Http\Controllers\Seller\SellerLogoutController;
use App\Http\Controllers\Seller\SellerOnboardingStatusController;
use App\Http\Controllers\Seller\SellerProfileController;
use App\Http\Controllers\Seller\SellerRegistrationController;
use App\Http\Controllers\Seller\SellerResetPasswordController;
use App\Http\Controllers\Seller\SellerTwoFactorDisableController;
use App\Http\Controllers\Seller\SellerTwoFactorEnableController;
use App\Http\Controllers\Seller\SellerTwoFactorOtpController;
use App\Http\Controllers\Seller\SellerUpdateController;
use App\Http\Controllers\Seller\SellerVerifyController;
use App\Http\Controllers\Seller\StoreAddressShowController;
use App\Http\Controllers\Seller\StoreCreateController;
use App\Http\Controllers\Seller\StoreShowController;
use App\Http\Controllers\Seller\StoreStripeConnectController;
use App\Http\Controllers\Seller\StoreUpdateController;

Route::prefix('seller')->group(function () {
    // Seller auth — public, rate-limited
    Route::middleware(['throttle:seller-auth'])->group(function () {
        Route::post('/register', SellerRegistrationController::class)->name('seller.register');
        Route::post('/login', SellerLoginController::class)->name('seller.login');
        Route::post('/login/otp', SellerLoginOTPController::class)->name('seller.login.otp');
        Route::post('/password/forgot', SellerForgotPasswordController::class)->name('seller.password.forgot');
        Route::post('/password/reset', SellerResetPasswordController::class)->name('seller.password.reset');
        Route::post('/google/login', SellerGoogleLoginController::class)->name('seller.google.login');
    });

    // Store — registered before the /{seller:uuid} wildcard below, so PUT /store
    // isn't swallowed by it.
    Route::middleware(['auth:marketplace'])->group(function () {
        Route::get('/store/address', StoreAddressShowController::class)->name('seller.store.address.show');
        Route::get('/store', StoreShowController::class)->name('seller.store.show');
        Route::post('/store', StoreCreateController::class)->name('seller.store.create');
        Route::put('/store', StoreUpdateController::class)->name('seller.store.update');
        Route::post('/stripe/connect', StoreStripeConnectController::class)->name('seller.store.stripe.connect');

        Route::prefix('store/section')->group(function () {
            Route::put('/{section:uuid}/move', SectionMoveController::class)->name('store.section.move');
            Route::post('/{section:uuid}/items', SectionItemAddController::class)->name('store.section.item.add');
            Route::post('/', SectionCreateController::class)->name('store.section.create');
            Route::delete('/{section:uuid}', SectionDeleteController::class)->name('store.section.delete');
            Route::delete('/{section:uuid}/items', SectionItemDeleteController::class)->name('store.section.item.delete');
            Route::put('/reorder', SectionOrderController::class)->name('store.section.order');
            Route::put('/{section:uuid}/items/reorder', SectionProductOrderController::class)->name('store.section.order.product');
            Route::put('/{section:uuid}', SectionUpdateController::class)->name('store.section.update');
            Route::get('/{section:uuid}/items', SectionItemsController::class)->name('store.section.items');
            Route::get('/', SectionController::class)->name('store.section.list');
        });
    });

    // Seller — profile, security, account
    Route::middleware(['auth:marketplace'])->group(function () {
        Route::get('/me', SellerProfileController::class)->name('seller.profile');
        Route::post('/logout', SellerLogoutController::class)->name('seller.logout');
        Route::post('/verify', SellerVerifyController::class)->name('seller.verify');
        Route::get('/onboarding/status', SellerOnboardingStatusController::class)->name('seller.onboarding.status');

        Route::post('/password/current', SellerCurrentPasswordController::class)->name('seller.password.current');
        Route::post('/password/change', SellerChangePasswordController::class)->name('seller.password.change');
        Route::post('/2fa/otp', SellerTwoFactorOtpController::class)->name('seller.2fa.otp');
        Route::post('/2fa/enable', SellerTwoFactorEnableController::class)->name('seller.2fa.enable');
        Route::post('/2fa/disable', SellerTwoFactorDisableController::class)->name('seller.2fa.disable');

        Route::put('/{seller:uuid}', SellerUpdateController::class)->name('seller.update');
    });

    // Product
    Route::prefix('product')->middleware(['auth:marketplace'])->group(function () {
        Route::post('/', ProductCreateController::class)->name('product.create');
        Route::get('/', ProductListController::class)->name('product.list');
        Route::get('/{item:uuid}', ProductShowController::class)->name('product.show');
        Route::put('/{item:uuid}', ProductUpdateController::class)->name('product.update');
        Route::put('/{item:uuid}/archive', ProductArchiveToggleController::class)->name('product.update.archive');
    });
});

Route::prefix('misc')->middleware(['auth:marketplace'])->group(function () {
    Route::get('/country', CountryListController::class)->name('misc.country.list');
    Route::get('/state', StateListController::class)->name('misc.state.list');
    Route::get('/currency', CurrencyListController::class)->name('misc.currency.list');
    Route::get('/language', LanguageListController::class)->name('misc.language.list');
});
