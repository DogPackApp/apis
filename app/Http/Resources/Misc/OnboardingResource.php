<?php

namespace App\Http\Resources\Misc;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Onboarding',
    properties: [
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
        new OA\Property(property: 'is_product', type: 'integer', enum: [0, 1]),
        new OA\Property(property: 'is_shipping', type: 'integer', enum: [0, 1]),
        new OA\Property(property: 'is_store_setting', type: 'integer', enum: [0, 1]),
        new OA\Property(property: 'is_finance', type: 'integer', enum: [0, 1]),
        new OA\Property(property: 'is_subscribe', type: 'integer', enum: [0, 1]),
    ]
)]
class OnboardingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'is_product' => $this->is_product,
            'is_shipping' => $this->is_shipping,
            'is_store_setting' => $this->is_store_setting,
            'is_finance' => $this->is_finance,
            'is_subscribe' => $this->is_subscribe,
        ];
    }
}
