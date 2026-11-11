<?php

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'customer_id' => $this->customer_id,
            'seller_id' => $this->seller_id,
            'last_message' => $this->last_message,
            'last_message_at' => $this->last_message_at,
            'unread_count' => $this->unread_chats_count,
            'is_active' => $this->is_active,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
        ];
    }
}
