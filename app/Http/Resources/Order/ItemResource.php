<?php

namespace App\Http\Resources\Order;

use App\Http\Resources\Product\DiscountResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variants = [];

        $options = $this->__attributeOptions($this->item_options);

        foreach ($options as $option) {
            $variants[$option->attribute->name] = $option->attribute_value;
        }

        $variantUuid = $this->__itemVariant($this->item_options)?->uuid ?? null;
        $promoApplied = ! empty($this->promo) && ($this->promo['applied'] ?? false);
        $isSubscribeSave = (bool) $this->is_subscribe_save;

        return [
            'uuid' => $this->uuid,
            'quantity' => $this->quantity,
            'original_price' => $this->original_price / 100,
            'discount' => $this->discount / 100,
            'price' => $this->price / 100,
            'subtotal' => $this->subtotal / 100,
            'item_options' => $variants ?? [],
            'variant_uuid' => $variantUuid,
            'tax' => [
                'original' => isset($this->tax['original']) ? $this->tax['original'] / 100 : 0,
                'converted' => isset($this->tax['converted']) ? $this->tax['converted'] / 100 : 0,
            ],
            'fulfilment_status' => $this->fulfilment_status ?? null,
            'refunded' => $this->refunded ?? 0,
            'product_snapshot' => $this->product_snapshot,
            // PS: either discounts or promo will be present,
            // if promo is applied, then discounts will be null
            // A Subscribe & Save line never uses the product's sale, so it carries
            // the percent the order was placed at, in the same shape.
            'discounts' => match (true) {
                $promoApplied => null,
                $isSubscribeSave => $this->subscribeSaveDiscount(),
                default => new DiscountResource($this->whenLoaded('discountRelation')),
            },
            'promo' => $this->promo ?? null,
        ];
    }

    /**
     * Amounts are the store's currency, like the rest of the line. A first order
     * capped at the country's max discount took off less than its percent, and
     * then exactly the cap (converted to the store's currency).
     */
    private function subscribeSaveDiscount(): array
    {
        $percent = (int) $this->subscribe_save_seller_percent + (int) $this->subscribe_save_dogpack_percent;
        $quantity = max(1, (int) $this->quantity);
        $discountAmount = round((float) $this->discount * $quantity / 100, 2);
        $fullAmount = round((float) $this->original_price * $quantity * $percent / 10000, 2);
        $capped = $fullAmount - $discountAmount >= 0.02;

        return [
            'uuid' => null,
            'value' => $percent,
            'is_limited' => 1,
            'start_date' => null,
            'end_date' => null,
            'discount_amount' => $discountAmount,
            'discount_capped' => $capped,
            'max_discount_amount' => $capped ? $discountAmount : null,
        ];
    }
}
