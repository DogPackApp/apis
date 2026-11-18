<?php

use App\Http\Controllers\Order\Cancel\CancelOrderController;
use App\Http\Controllers\Order\OrderExportCsvController;
use App\Http\Controllers\Order\OrderListController;
use App\Http\Controllers\Order\OrderManualFulfillController;
use App\Http\Controllers\Order\OrderPackingSlipController;
use App\Http\Controllers\Order\OrderShowController;
use App\Http\Controllers\Order\OrderUpdateProductWeightController;
use App\Http\Controllers\Order\Refunds\RefundOrderPartialController;
use App\Http\Controllers\Order\Timeline\TimelineController;
use App\Http\Controllers\Order\Timeline\TimelineEmailRenderController;
use App\Http\Controllers\Order\Timeline\TimelineListController;
use Illuminate\Support\Facades\Route;

Route::prefix('orders')->group(function () {
    Route::prefix('timeline')->group(function () {
        Route::get('/', TimelineController::class)->name('orders.timeline');
        Route::get('/{order:uuid}', TimelineListController::class)->name('orders.timeline.list');
        Route::get('/{order:uuid}/email/{event}', TimelineEmailRenderController::class)->name('orders.email.render');
        Route::get('/{order:uuid}/email/{event}/{timeline:uuid}', TimelineEmailRenderController::class)->name('orders.email.render.specific');
    });

    Route::get('/', OrderListController::class)->name('orders.list');
    Route::get('/{order:uuid}', OrderShowController::class)->name('orders.show');
    Route::post('/{order:uuid}/manual/fulfill', OrderManualFulfillController::class)->name('orders.manual.fulfill');
    Route::get('/{order:uuid}/packing/{size}', OrderPackingSlipController::class)->name('orders.manual.packing');
    Route::post('/fulfill/weight/{order:uuid}', OrderUpdateProductWeightController::class)->name('orders.fulfill.weight.update');

    Route::prefix('cancel')->group(function () {
        Route::post('/{order:uuid}/', CancelOrderController::class)->name('orders.cancel');
    });

    Route::prefix('refund')->group(function () {
        Route::post('/{order:uuid}/partial', RefundOrderPartialController::class)->name('orders.refund.partial');
    });

    Route::post('/export-csv', OrderExportCsvController::class)->name('orders.export.csv');
});
