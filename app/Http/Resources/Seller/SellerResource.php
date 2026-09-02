<?php

namespace App\Http\Resources\Seller;

use App\Http\Resources\Store\StoreResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Seller',
    properties: [
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
        new OA\Property(property: 'first_name', type: 'string'),
        new OA\Property(property: 'last_name', type: 'string'),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'status', type: 'integer', description: '0 = unverified, 1 = verified'),
        new OA\Property(property: 'twofa_enabled', type: 'integer', description: '0 = disabled, 1 = enabled'),
        new OA\Property(property: 'store', ref: '#/components/schemas/Store', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'is_master_login', type: 'boolean'),
    ]
)]
class SellerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
            'twofa_enabled' => $this->twofa_enabled,
            'store' => $this->whenLoaded('store', fn () => $this->store ? StoreResource::make($this->store) : null),
            'updated_at' => ($this->updated_at instanceof \DateTimeInterface)
                ? $this->updated_at->format('Y-m-d H:i:s')
                : $this->updated_at,
            'created_at' => ($this->created_at instanceof \DateTimeInterface)
                ? $this->created_at->format('Y-m-d H:i:s')
                : $this->created_at,
            'is_master_login' => $request->user()
                ? $request->user()->tokenCan('master-login')
                : (isset($this->additional['is_master_login'])
                    ? $this->additional['is_master_login']
                    : false
                ),
        ];
    }
}
