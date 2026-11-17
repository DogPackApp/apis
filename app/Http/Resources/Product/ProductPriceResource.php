<?php

namespace App\Http\Resources\Product;

use App\Models\Product\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProductPrice',
    properties: [
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
        new OA\Property(property: 'sku', type: 'string', nullable: true),
        new OA\Property(property: 'gtin', type: 'string', nullable: true),
        new OA\Property(property: 'is_global_pricing', type: 'boolean'),
        new OA\Property(property: 'domestic_price', type: 'number', format: 'float', example: 12.5),
        new OA\Property(property: 'global_price', type: 'number', format: 'float', example: 15),
        new OA\Property(property: 'stock_quantity', type: 'integer', nullable: true),
        new OA\Property(property: 'weight', type: 'number', format: 'float'),
        new OA\Property(property: 'allow_overselling', type: 'boolean'),
        new OA\Property(property: 'unit', type: 'string', enum: ['G', 'KG', 'MG', 'OZ', 'LB']),
    ]
)]
class ProductPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'sku' => $this->sku,
            'gtin' => $this->gtin,
            'is_global_pricing' => $this->is_global_pricing,
            'domestic_price' => $this->domestic_price / 100,
            'global_price' => $this->global_price / 100,
            'stock_quantity' => $this->stock_quantity,
            'weight' => $this->weight / 100,
            'allow_overselling' => $this->allow_overselling ?? 0,
            'unit' => $this->unit ? array_search($this->unit, Item::ITEM_WEIGHT) : 'G',
        ];
    }
}
