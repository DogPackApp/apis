<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\Chat\ThreadResource;
use App\Models\Chat\Thread;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChatListController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request, Store $store)
    {
        try {
            $uuid = $store->uuid;
            $threads = Thread::where('seller_id', $uuid)->with('customer')->withCount(['unreadChats'])->orderby('last_message_at', 'desc')->get();

            return ThreadResource::collection($threads);
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
