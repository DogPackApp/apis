<?php

namespace App\Http\Resources\Store;

use App\Http\Resources\Product\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'is_return' => $this->is_return,
            'is_exchange' => $this->is_exchange,
            'is_default' => $this->is_default,
            'time' => $this->time,
            'items_count' => $this->items_count,
            'custom_text' => $this->custom_text,
            'items' => ProductResource::collection($this->whenLoaded('items')),
        ];
    }
}
