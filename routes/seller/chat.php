<?php

use App\Http\Controllers\Chat\ChatConversationController;
use App\Http\Controllers\Chat\ChatListController;
use App\Http\Controllers\Chat\ChatUnreadCountController;
use App\Http\Controllers\Chat\InitiateMessageController;
use App\Http\Controllers\Chat\ReportThreadController;
use App\Http\Controllers\Chat\SendChatEmailController;
use App\Http\Controllers\Chat\UnBlockThreadController;
use Illuminate\Support\Facades\Route;

Route::prefix('message')->middleware('auth:marketplace')->group(function () {
    Route::post('/initiate', InitiateMessageController::class)->name('message.initiate');
});

Route::prefix('chat')->middleware('auth:marketplace')->group(function () {
    Route::get('/list', ChatListController::class)->name('chat.list');
    Route::get('/list/{uuid}', ChatConversationController::class)->name('chat.conversation');
    Route::get('/unreadCount', ChatUnreadCountController::class)->name('chat.unread.count');
    Route::post('/report/{uuid}', ReportThreadController::class)->name('chat.report');
    Route::put('/unblock/{uuid}', UnBlockThreadController::class)->name('chat.unblock');
});

Route::post('/seller/chat/email', SendChatEmailController::class)->name('seller.chat.email');
