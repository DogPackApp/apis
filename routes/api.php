<?php

use App\Http\Controllers\Seller\SellerForgotPasswordController;
use App\Http\Controllers\Seller\SellerGoogleLoginController;
use App\Http\Controllers\Seller\SellerLoginController;
use App\Http\Controllers\Seller\SellerLoginOTPController;
use App\Http\Controllers\Seller\SellerLogoutController;
use App\Http\Controllers\Seller\SellerOnboardingStatusController;
use App\Http\Controllers\Seller\SellerProfileController;
use App\Http\Controllers\Seller\SellerRegistrationController;
use App\Http\Controllers\Seller\SellerResetPasswordController;
use App\Http\Controllers\Seller\SellerUpdateController;
use App\Http\Controllers\Seller\SellerVerifyController;

Route::prefix('seller')->group(function () {
    Route::post('/register', SellerRegistrationController::class)->name('seller.register');
    Route::post('/login', SellerLoginController::class)->name('seller.login');
    Route::post('/login/otp', SellerLoginOTPController::class)->name('seller.login.otp');
    Route::post('/password/forgot', SellerForgotPasswordController::class)->name('seller.password.forgot');
    Route::post('/password/reset', SellerResetPasswordController::class)->name('seller.password.reset');
    Route::post('/google/login', SellerGoogleLoginController::class)->name('seller.google.login');

    Route::middleware(['auth:marketplace'])->group(function () {
        Route::get('/me', SellerProfileController::class)->name('seller.profile');
        Route::post('/logout', SellerLogoutController::class)->name('seller.logout');
        Route::post('/verify', SellerVerifyController::class)->name('seller.verify');
        Route::get('/onboarding/status', SellerOnboardingStatusController::class)->name('seller.onboarding.status');
        Route::put('/{seller:uuid}', SellerUpdateController::class)->name('seller.update');
    });
});
