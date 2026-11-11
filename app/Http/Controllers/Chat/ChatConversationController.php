<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Http\Resources\Chat\ChatResource;
use App\Models\Store\Store;
use App\Services\Chat\ChatConversationService;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class ChatConversationController extends Controller
{
    public function __invoke($uuid)
    {
        try {
            $store = app(Store::class);

            $chats = app(ChatConversationService::class)->fetchChats($store->uuid, $uuid);

            return ChatResource::collection($chats);
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
