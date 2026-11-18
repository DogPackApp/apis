<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'StoreAddress',
    properties: [
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
        new OA\Property(property: 'address', type: 'string'),
        new OA\Property(property: 'street1', type: 'string'),
        new OA\Property(property: 'street2', type: 'string', nullable: true),
        new OA\Property(property: 'zipcode', type: 'string'),
        new OA\Property(property: 'latitude', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'longitude', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'city', type: 'string'),
        new OA\Property(property: 'country', type: 'string'),
    ]
)]
class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>|null
     */
    public function toArray(Request $request): ?array
    {
        if ($this->resource === null) {
            return null;
        }

        return [
            'uuid' => $this->uuid,
            'address' => $this->address,
            'street1' => $this->street1,
            'street2' => $this->street2,
            'zipcode' => $this->zipcode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'city' => $this->city,
            'country' => $this->country,
        ];
    }
}
