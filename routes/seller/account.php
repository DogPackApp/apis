<?php

use App\Http\Controllers\Seller\SellerLogoutController;
use App\Http\Controllers\Seller\SellerOnboardingStatusController;
use App\Http\Controllers\Seller\SellerProfileController;
use App\Http\Controllers\Seller\SellerUpdateController;
use App\Http\Controllers\Seller\SellerVerifyController;
use Illuminate\Support\Facades\Route;

Route::get('/me', SellerProfileController::class)->name('seller.profile');
Route::post('/logout', SellerLogoutController::class)->name('seller.logout');
Route::post('/verify', SellerVerifyController::class)->name('seller.verify');
Route::get('/onboarding/status', SellerOnboardingStatusController::class)->name('seller.onboarding.status');

Route::put('/{seller:uuid}', SellerUpdateController::class)->name('seller.update');
