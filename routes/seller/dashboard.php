<?php

use App\Http\Controllers\Analytics\TotalSalesController;
use App\Http\Controllers\Dashboard\PurchaseBlockController;
use App\Http\Controllers\Dashboard\ReviewsBlockController;
use App\Http\Controllers\Dashboard\SalesBlockController;
use App\Http\Controllers\Dashboard\UnfulfilledBlockController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')->group(function () {
    Route::get('/purchases', PurchaseBlockController::class)->name('dashboard.purchases');
    Route::get('/sales', SalesBlockController::class)->name('dashboard.sales');
    Route::get('/reviews', ReviewsBlockController::class)->name('dashboard.reviews');
    Route::get('/unfulfilled', UnfulfilledBlockController::class)->name('dashboard.unfulfilled');
});

Route::prefix('analytics')->group(function () {
    Route::get('/totalSales', TotalSalesController::class)->name('analytics.totalSales');
});
