<?php

use App\Http\Controllers\Seller\Section\SectionController;
use App\Http\Controllers\Seller\Section\SectionCreateController;
use App\Http\Controllers\Seller\Section\SectionDeleteController;
use App\Http\Controllers\Seller\Section\SectionItemAddController;
use App\Http\Controllers\Seller\Section\SectionItemDeleteController;
use App\Http\Controllers\Seller\Section\SectionItemsController;
use App\Http\Controllers\Seller\Section\SectionMoveController;
use App\Http\Controllers\Seller\Section\SectionOrderController;
use App\Http\Controllers\Seller\Section\SectionProductOrderController;
use App\Http\Controllers\Seller\Section\SectionUpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('store/section')->group(function () {
    Route::put('/{section:uuid}/move', SectionMoveController::class)->name('store.section.move');
    Route::post('/{section:uuid}/items', SectionItemAddController::class)->name('store.section.item.add');
    Route::post('/', SectionCreateController::class)->name('store.section.create');
    Route::delete('/{section:uuid}', SectionDeleteController::class)->name('store.section.delete');
    Route::delete('/{section:uuid}/items', SectionItemDeleteController::class)->name('store.section.item.delete');
    Route::put('/reorder', SectionOrderController::class)->name('store.section.order');
    Route::put('/{section:uuid}/items/reorder', SectionProductOrderController::class)->name('store.section.order.product');
    Route::put('/{section:uuid}', SectionUpdateController::class)->name('store.section.update');
    Route::get('/{section:uuid}/items', SectionItemsController::class)->name('store.section.items');
    Route::get('/', SectionController::class)->name('store.section.list');
});
