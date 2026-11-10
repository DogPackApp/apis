<?php

namespace App\Http\Resources\Product;

use App\Http\Resources\Store\PolicyResource;
use App\Http\Resources\Store\SectionResource;
use App\Models\Product\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Product',
    properties: [
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'type', type: 'string', enum: ['DIGITAL', 'PHYSICAL']),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'origin', type: 'string'),
        new OA\Property(property: 'inventory', type: 'string', example: '5 in Stock'),
        new OA\Property(property: 'status', type: 'string', enum: ['ACTIVE', 'DRAFT', 'ARCHIVED', 'DELETED']),
        new OA\Property(property: 'prices', ref: '#/components/schemas/ProductPrice'),
        new OA\Property(property: 'category', ref: '#/components/schemas/ProductCategory'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'is_shopify', type: 'boolean'),
    ]
)]
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'type' => array_search($this->type, Item::TYPES),
            'description' => $this->description,
            'origin' => $this->origin ?? 'US',
            'inventory' => $this->stock(),
            'status' => array_search($this->status, Item::STATUS),
            'customization' => new ProductCustomizationResource($this->whenLoaded('customization')),
            'prices' => new ProductPriceResource($this->whenLoaded('price')),
            'category' => new ProductCategoryResource($this->whenLoaded('category')),
            'policy' => new PolicyResource($this->whenLoaded('policy')),
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
            'attributes' => AttributesResource::collection($this->whenLoaded('attributes')),
            'vary_by' => new VaryByResource($this->whenLoaded('varyBy')),
            'itemVariants' => VariantsResource::collection(
                $this->whenLoaded('itemVariants', function ($collection) {
                    return $collection->filter(function ($variant) {
                        $attributesOption = $variant->relationLoaded('attributesOption')
                            ? $variant->attributesOption
                            : collect();

                        return $attributesOption->count() > 0;
                    })->values();
                })
            ),
            'tags' => ProductTagResource::collection($this->whenLoaded('tags')),
            'medias' => ProductMediaResource::collection($this->whenLoaded('medias')),
            'media' => new ProductMediaResource($this->whenLoaded('media')),
            'discount' => new DiscountResource($this->whenLoaded('discount')),
            'updated_at' => $this->updated_at,
            'is_shopify' => $this->isShopifyProduct(),
        ];
    }
}
