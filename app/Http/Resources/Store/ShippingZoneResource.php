<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'shipping_zone_country' => ShippingZoneCountryResource::collection($this->whenLoaded('shippingZoneCountry')),
            'shipping_zone_rates' => ShippingZoneRatesResource::collection($this->whenLoaded('shippingZoneRates')),
        ];
    }
}
