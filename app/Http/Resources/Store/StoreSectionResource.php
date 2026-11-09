<?php

namespace App\Http\Resources\Store;

use App\Http\Resources\Product\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_visible' => $this->is_visible,
            'order' => $this->order,
            'items' => ProductResource::collection($this->whenLoaded('items')),
            'items_count' => $this->when(
                ! is_null($this->items_count),
                $this->items_count
            ),
        ];
    }
}
