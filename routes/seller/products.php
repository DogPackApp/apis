<?php

use App\Http\Controllers\Product\ProductArchiveToggleController;
use App\Http\Controllers\Product\ProductCreateController;
use App\Http\Controllers\Product\ProductListController;
use App\Http\Controllers\Product\ProductShowController;
use App\Http\Controllers\Product\ProductUpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('product')->group(function () {
    Route::post('/', ProductCreateController::class)->name('product.create');
    Route::get('/', ProductListController::class)->name('product.list');
    Route::get('/{item:uuid}', ProductShowController::class)->name('product.show');
    Route::put('/{item:uuid}', ProductUpdateController::class)->name('product.update');
    Route::put('/{item:uuid}/archive', ProductArchiveToggleController::class)->name('product.update.archive');
});
