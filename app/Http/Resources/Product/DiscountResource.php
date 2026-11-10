<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'value' => $this->value !== null ? $this->value / 100 : null,
            'is_limited' => $this->is_limited,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
        ];
    }
}
