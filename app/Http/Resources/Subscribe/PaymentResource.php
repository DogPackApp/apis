<?php

namespace App\Http\Resources\Subscribe;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'stripe_invoice_id' => $this->stripe_invoice_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'paid_at' => $this->paid_at ?? $this->updated_at,
            'billing_reason' => $this->billing_reason,
            'receipt_url' => $this->receipt_url,
            'failure_message' => $this->failure_message,
        ];
    }
}
