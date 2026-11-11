<?php

namespace App\Models\V2;

use Illuminate\Support\Facades\Log;

class Notification
{
    /**
     * The seller reply job calls this. Device push and the social inbox stay in backend-api.
     *
     * @param  array<string, mixed>  $data
     */
    public static function sendNotification($user_id, $sender_id = '', $type = '', $title = '', $message = '', $data = []): void
    {
        Log::info('Review reply notification was not pushed from this app.', [
            'user_id' => $user_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
