<?php

namespace App\Http\Resources\Store;

use App\Http\Resources\Misc\MiscResource;
use App\Models\Store\ShippingZoneRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingZoneRatesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'shipping_type' => $this->shipping_type,
            'price' => $this->price,
            'condition_type' => $this->condition_type
                ? array_search($this->condition_type, ShippingZoneRate::ZONE_CONDITION_TYPE)
                : null,
            'low_value' => $this->low_value,
            'high_value' => $this->high_value,
            'weight' => $this->weight,
            'currency' => new MiscResource($this->whenLoaded('currency')),
            'shipping_rate' => new ShippingRatesResource($this->whenLoaded('shippingRate')),
        ];
    }
}
