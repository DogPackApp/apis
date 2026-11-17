<?php

use App\Http\Controllers\Product\Bulk\ProductBulkListController;
use App\Http\Controllers\Product\Bulk\ProductBulkUpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('bulk')->group(function () {
    Route::post('/', ProductBulkListController::class)->name('bulk.list');
    Route::put('/', ProductBulkUpdateController::class)->name('bulk.update');
});
