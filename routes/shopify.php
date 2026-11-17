<?php

use App\Http\Controllers\Shopify\ManualFulfillOrderController;
use App\Http\Controllers\Shopify\ProductDeleteController;
use App\Http\Controllers\Shopify\ProductSyncController;
use Illuminate\Support\Facades\Route;

Route::post('/product/create', ProductSyncController::class)->name('shopify.product.create');
Route::post('/product/delete', ProductDeleteController::class)->name('shopify.product.delete');
Route::post('/order/fulfill', ManualFulfillOrderController::class)->name('shopify.order.fulfill');
