<?php

namespace App\Http\Resources\Store;

use App\Http\Resources\Misc\CountryResource;
use App\Http\Resources\Misc\StateResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingZoneCountryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country' => new CountryResource($this->whenLoaded('country')),
            'excluded_states' => StateResource::collection($this->whenLoaded('excludedStates')),
        ];
    }
}
