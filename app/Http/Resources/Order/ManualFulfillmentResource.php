<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManualFulfillmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tracking' => $this->tracking ?? null,
            'tracking_url' => $this->tracking_url ?? null,
            'carrier' => $this->carrier ?? null,
        ];
    }
}
