<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariantsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributesOption = $this->relationLoaded('attributesOption')
            ? $this->attributesOption
            : collect();

        $fId = null;

        if ($attributesOption->count() > 0) {
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
            'sku' => $this->sku,
            'gtin' => $this->gtin,
            'weight' => $this->weight,
            'domestic_price' => $this->domestic_price !== null ? $this->domestic_price / 100 : null,
            'global_price' => $this->global_price !== null ? $this->global_price / 100 : null,
            'stock_quantity' => $this->stock_quantity,
            'order' => $this->order,
            'media' => new ProductMediaResource($this->whenLoaded('media')),
            'attributesOption' => CombinationResource::collection($this->whenLoaded('attributesOption')),
            'is_visible' => $this->is_visible ?? 0,
            'allow_overselling' => $this->allow_overselling ?? 0,
            $this->mergeWhen($fId !== null, [
                'f_id' => $fId,
            ]),
        ];
    }
}
