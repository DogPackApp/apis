<?php

use App\Http\Controllers\Seller\SellerMasterLoginController;
use App\Http\Controllers\Seller\SellerMasterLoginOtpController;
use Illuminate\Support\Facades\Route;

Route::post('/master-login', SellerMasterLoginController::class)->name('seller.master-login');
Route::post('/master-login/otp', SellerMasterLoginOtpController::class)->name('seller.master-login.otp');
