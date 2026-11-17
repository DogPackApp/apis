<?php

use App\Http\Controllers\Finance\FinanceOverviewController;
use App\Http\Controllers\Finance\FinanceOverviewDetailController;
use App\Http\Controllers\Finance\FinanceOverviewExportCsvController;
use App\Http\Controllers\Finance\FinanceOverviewExportDetailsCsvController;
use App\Http\Controllers\Stripe\StripePayoutController;
use Illuminate\Support\Facades\Route;

Route::post('/stripe/payouts', StripePayoutController::class)->name('stripe.payouts');

Route::prefix('finance')->group(function () {
    Route::get('/', FinanceOverviewController::class)->name('finance.overview.list');
    Route::post('/export', FinanceOverviewExportCsvController::class)->name('finance.overview.export.csv');
    Route::post('/export-details', FinanceOverviewExportDetailsCsvController::class)->name('finance.overview.export.details.csv');
    Route::post('/{payout:uuid}', FinanceOverviewDetailController::class)->name('finance.overview.detail');
});
