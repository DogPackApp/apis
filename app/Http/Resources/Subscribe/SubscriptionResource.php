<?php

namespace App\Http\Resources\Subscribe;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stripe_subscription_id' => $this->stripe_subscription_id,
            'subscription_status' => $this->subscription_status,
            'stripe_price_id' => $this->pricing->stripe_price_id,
            'current_period_start' => $this->current_period_start,
            'current_period_end' => $this->current_period_end,
            'name' => $this->pricing->name,
        ];
    }
}
