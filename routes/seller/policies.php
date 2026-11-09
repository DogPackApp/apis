<?php

use App\Http\Controllers\Seller\Policy\PolicyController;
use App\Http\Controllers\Seller\Policy\PolicyCreateController;
use App\Http\Controllers\Seller\Policy\PolicyDeleteController;
use App\Http\Controllers\Seller\Policy\PolicyItemAddController;
use App\Http\Controllers\Seller\Policy\PolicyItemsController;
use App\Http\Controllers\Seller\Policy\PolicyShowController;
use App\Http\Controllers\Seller\Policy\PolicyToggleDefaultController;
use App\Http\Controllers\Seller\Policy\PolicyUpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('policy')->group(function () {
    Route::post('/', PolicyCreateController::class)->name('policies.create');
    Route::get('/', PolicyController::class)->name('policies.list');
    Route::get('/{policy:uuid}/items', PolicyItemsController::class)->name('policies.show.items');
    Route::post('/{policy:uuid}/items', PolicyItemAddController::class)->name('policies.item.add');
    Route::get('/{policy:uuid}', PolicyShowController::class)->name('policies.show');
    Route::put('/{policy:uuid}', PolicyUpdateController::class)->name('policies.update');
    Route::put('/{policy:uuid}/default', PolicyToggleDefaultController::class)->name('policies.update.default');
    Route::delete('/{policy:uuid}', PolicyDeleteController::class)->name('policies.delete');
});
