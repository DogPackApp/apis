<?php

namespace App\Http\Resources\Order;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
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
            'amount' => $this->amount / 100,
            'tax' => $this->tax / 100,
            'tax_converted' => $this->tax_converted / 100,
            'converted' => $this->converted / 100,
            'reason' => $this->reason,
        ];
    }
}
