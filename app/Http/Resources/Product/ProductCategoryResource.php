<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ProductCategory',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
        new OA\Property(property: 'slug', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'ht_code', type: 'string', nullable: true),
        new OA\Property(property: 'order', type: 'string', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean'),
        new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
    ]
)]
class ProductCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'name' => $this->name,
            'ht_code' => $this->ht_code,
            'order' => $this->order,
            'is_active' => $this->is_active,
            'parent_id' => $this->parent_id,
            'id' => $this->id,
        ];
    }
}
