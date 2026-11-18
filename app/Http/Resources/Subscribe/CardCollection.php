<?php

namespace App\Http\Resources\Subscribe;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CardCollection extends ResourceCollection
{
    private ?string $defaultPaymentMethodId;

    public function __construct($resource, $defaultPaymentMethodId)
    {
        parent::__construct($resource);
        $this->defaultPaymentMethodId = $defaultPaymentMethodId;
    }

    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($card) {
            return array_merge(
                (new CardResource($card))->toArray(request()),
                ['is_default' => $card->id === $this->defaultPaymentMethodId]
            );
        })->toArray();
    }
}
