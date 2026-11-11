<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Chat\Thread;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChatUnreadCountController extends Controller
{
    public function __invoke(Request $request, Store $store)
    {
        try {
            $uuid = $store->uuid;
            $threads = Thread::where('seller_id', $uuid)->where('is_active', true)->withCount(['unreadChats'])->get();

            $totalUnreadCount = $threads->sum('unread_chats_count');

            return response()->json(['total_unread_count' => $totalUnreadCount]);
        } catch (Exception $e) {
            $message = app()->environment() !== 'production'
                ? $e->getMessage()
                : 'Something went wrong';

            return response()->json([
                'errors' => ['message' => $message],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
