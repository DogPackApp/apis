<?php

use App\Http\Controllers\Review\ReplyController;
use App\Http\Controllers\Review\ReportController;
use App\Http\Controllers\Review\ReportListController;
use App\Http\Controllers\Review\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/store/reviews', ReviewController::class)->name('store.review.list');

Route::prefix('reviews')->group(function () {
    Route::get('/report/', ReportListController::class)->name('reviews.report.list');
    Route::post('/{itemReview:uuid}/reply/', ReplyController::class)->name('reviews.reply');
    Route::post('/{itemReview:uuid}/report/', ReportController::class)->name('reviews.report');
});
