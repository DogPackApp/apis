<?php

use App\Http\Controllers\Product\Import\CSVBatchListController;
use App\Http\Controllers\Product\Import\CSVBatchUpdateController;
use App\Http\Controllers\Product\Import\CSVImportController;
use App\Http\Controllers\Product\Import\CSVProcessImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('import')->group(function () {
    Route::post('/csv', CSVImportController::class)->name('imports.csv');
    Route::get('/csv/{batchId}', CSVBatchListController::class)->name('imports.csv.batch');
    Route::post('/csv/{batchId}', CSVBatchUpdateController::class)->name('imports.csv.batch.update');
    Route::post('/csv/process/{batchId}', CSVProcessImportController::class)->name('imports.csv.batch.process');
});
