<?php

namespace App\Services\Chat;

use App\Models\Chat\Chat;

class ChatConversationService
{
    public function fetchChats($storeUuid, $threadUuid)
    {
        Chat::where('thread_uuid', $threadUuid)
            ->where('sender_id', '!=', $storeUuid)
            ->where('is_read', 0)
            ->update([
                'is_read' => 1,
            ]);

        $chats = Chat::query()
            ->where('thread_uuid', $threadUuid)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $chats;
    }
}
