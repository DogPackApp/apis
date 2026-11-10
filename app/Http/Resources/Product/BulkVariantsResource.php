<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BulkVariantsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributesOption = $this->whenLoaded('attributesOption');

        $fId = null;

        if (count($attributesOption) > 0) {
            if ($attributesOption->count() === 2) {
                $fId = $attributesOption[0]->attribute_value.'_'.$attributesOption[1]->attribute_value;
            } elseif ($attributesOption->count() === 3) {
                $fId = $attributesOption[0]->attribute_value.'_'.$attributesOption[1]->attribute_value.'_'.$attributesOption[2]->attribute_value;
            } else {
                $fId = $attributesOption[0]->attribute_value;
            }
        }

        return [
            'uuid' => $this->uuid,
            // 👇 only add f_id if not null
            $this->mergeWhen($fId !== null, [
                'f_id' => $fId,
            ]),
            'image' => new ProductMediaResource($this->whenLoaded('media')),
            'is_visible' => $this->is_visible ?? 0,
            'label' => $this->getLabels(),
            'sku' => $this->sku,
            'gtin' => $this->gtin,
            'weight' => $this->weight,
            'domestic_price' => $this->domestic_price / 100,
            'global_price' => $this->global_price / 100,
            'stock_quantity' => $this->stock_quantity,
            'allow_overselling' => $this->allow_overselling ?? 0,
        ];
    }

    public function getLabels(): string
    {
        $attributes = [];

        foreach ($this->attributesOption as $options) {
            $attributes[] = $options?->attribute_value ?? '';
        }

        if (empty($attributes)) {
            return '';
        }

        return implode(',', array_filter($attributes));
    }
}
