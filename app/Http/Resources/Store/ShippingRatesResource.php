<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingRatesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
