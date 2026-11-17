<?php

use App\Http\Controllers\Seller\Aws\PresignedController;
use App\Http\Controllers\Seller\Gallery\GalleryController;
use Illuminate\Support\Facades\Route;

Route::post('/store/gallery', GalleryController::class)->name('store.gallery.list');
Route::post('/aws/presigned', PresignedController::class)->name('aws.presigned');
