<?php

namespace App\Http\Resources\Subscribe;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardResource extends JsonResource
{
    private ?string $defaultPaymentMethodId;

    public function __construct($resource, ?string $defaultPaymentMethodId = null)
    {
        parent::__construct($resource);
        $this->defaultPaymentMethodId = $defaultPaymentMethodId;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_brand' => $this->card->brand,
            'exp_month' => $this->card->exp_month,
            'exp_year' => $this->card->exp_year,
            'last4' => $this->card->last4,
            'funding' => $this->card->funding,
            'cardholder_name' => $this->billing_details->name ?? null,
            'is_default' => $this->id === $this->defaultPaymentMethodId,
        ];
    }
}
