<?php

use App\Http\Controllers\Seller\SellerForgotPasswordController;
use App\Http\Controllers\Seller\SellerGoogleLoginController;
use App\Http\Controllers\Seller\SellerLoginController;
use App\Http\Controllers\Seller\SellerLoginOTPController;
use App\Http\Controllers\Seller\SellerRegistrationController;
use App\Http\Controllers\Seller\SellerResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::post('/register', SellerRegistrationController::class)->name('seller.register');
Route::post('/login', SellerLoginController::class)->name('seller.login');
Route::post('/login/otp', SellerLoginOTPController::class)->name('seller.login.otp');
Route::post('/password/forgot', SellerForgotPasswordController::class)->name('seller.password.forgot');
Route::post('/password/reset', SellerResetPasswordController::class)->name('seller.password.reset');
Route::post('/google/login', SellerGoogleLoginController::class)->name('seller.google.login');
