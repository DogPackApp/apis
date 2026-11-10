<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CombinationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'attribute_value' => $this->attribute_value,
            'attribute' => new AttributeResource($this->whenLoaded('attribute')),
        ];
    }
}
