<?php

namespace App\Http\Resources\Misc;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
