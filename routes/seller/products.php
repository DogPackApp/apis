<?php

use App\Http\Controllers\Product\ProductAiDescriptionController;
use App\Http\Controllers\Product\ProductArchiveToggleController;
use App\Http\Controllers\Product\ProductBulkMoveController;
use App\Http\Controllers\Product\ProductCategoryShowController;
use App\Http\Controllers\Product\ProductCreateController;
use App\Http\Controllers\Product\ProductExportCsvController;
use App\Http\Controllers\Product\ProductFacetShowController;
use App\Http\Controllers\Product\ProductListController;
use App\Http\Controllers\Product\ProductRecognizeCategoryController;
use App\Http\Controllers\Product\ProductShowController;
use App\Http\Controllers\Product\ProductUpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('product')->group(function () {
    Route::post('/', ProductCreateController::class)->name('product.create');
    Route::get('/', ProductListController::class)->name('product.list');
    Route::put('/bulk/move', ProductBulkMoveController::class)->name('product.bulk.move');
    Route::put('/{item:uuid}', ProductUpdateController::class)->name('product.update');
    Route::put('/{item:uuid}/archive', ProductArchiveToggleController::class)->name('product.update.archive');
    Route::get('/category', ProductCategoryShowController::class)->name('product.category.show');
    Route::get('/{item:uuid}', ProductShowController::class)->name('product.show');
    Route::post('/generate-description', ProductAiDescriptionController::class)->name('product.generate.description');
    Route::post('/recognize-category', ProductRecognizeCategoryController::class)->name('product.recognize.category');
    Route::post('/export-csv', ProductExportCsvController::class)->name('product.export.csv');
    Route::get('/category/{category:uuid}/facets', ProductFacetShowController::class)->name('product.category.facets.show');
});
