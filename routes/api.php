<?php

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
use App\Http\Controllers\Seller\StoreCreateController;
use App\Http\Controllers\Seller\StoreShowController;
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
        Route::get('/store', StoreShowController::class)->name('seller.store.show');
        Route::post('/store', StoreCreateController::class)->name('seller.store.create');
        Route::put('/store', StoreUpdateController::class)->name('seller.store.update');
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
});
