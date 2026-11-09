<?php

use App\Http\Controllers\Seller\SellerChangePasswordController;
use App\Http\Controllers\Seller\SellerCurrentPasswordController;
use App\Http\Controllers\Seller\SellerLogoutController;
use App\Http\Controllers\Seller\SellerOnboardingStatusController;
use App\Http\Controllers\Seller\SellerProfileController;
use App\Http\Controllers\Seller\SellerTwoFactorDisableController;
use App\Http\Controllers\Seller\SellerTwoFactorEnableController;
use App\Http\Controllers\Seller\SellerTwoFactorOtpController;
use App\Http\Controllers\Seller\SellerUpdateController;
use App\Http\Controllers\Seller\SellerVerifyController;
use Illuminate\Support\Facades\Route;

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
