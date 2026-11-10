<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'url' => $this->url,
            'type' => $this->type,
            'name' => $this->name,
            'order' => $this->pivot->order ?? $this->order ?? null,
        ];
    }
}
