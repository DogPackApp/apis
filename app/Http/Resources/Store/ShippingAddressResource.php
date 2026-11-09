<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'easypost_id' => $this->easypost_id,
            'address' => $this->address,
            'street1' => $this->street1,
            'street2' => $this->street2,
            'zipcode' => $this->zipcode,
            'city' => $this->city,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'province' => $this->province,
        ];
    }
}
