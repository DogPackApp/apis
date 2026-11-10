<?php

namespace App\Http\Resources\Product;

use App\Models\Product\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductBulkEditResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'type' => array_search($this->type, Item::TYPES),
            'origin' => $this->origin ?? 'US',
            'status' => array_search($this->status, Item::STATUS),
            'customization' => $this->getCustomization(),
            'prices' => new ProductPriceResource($this->whenLoaded('price')),
            'category' => $this->getCategories(),
            'policy' => $this->getPolicies(),
            'sections' => $this->getSections(),
            'vary_by' => new VaryByResource($this->whenLoaded('varyBy')),
            'combinations' => BulkVariantsResource::collection(
                $this->whenLoaded('itemVariants', function ($collection) {
                    return $collection->filter(function ($variant) {
                        $attributesOption = $variant->attributesOption ?? collect();

                        // Only keep if there’s something to build f_id from
                        return $attributesOption->count() > 0;
                    })->values();
                })
            ),
            'tags' => $this->getTags(),
            'medias' => $this->getMedias(),
            'discount' => $this->getDiscount(),
            'is_shopify' => $this->isShopifyProduct(),
        ];
    }

    private function getCustomization()
    {
        $customization = $this->customization;

        if (! $customization) {
            return null;
        }

        return [
            'is_enabled' => 1,
            'image' => $customization->attach_file ?? 0,
            'text' => $customization->description ?? null,
            'text_limit' => $customization->limit ?? null,
            'description_enabled' => $customization->description_enabled ?? 0,
        ];
    }

    private function getDiscount()
    {
        $discount = $this->discount;

        if (! $discount) {
            return null;
        }

        return [
            'value' => $discount?->value ? $discount?->value / 100 : 0,
            'is_limited' => $discount->is_limited ?? 0,
            'start_date' => $discount->start_date ?? null,
            'end_date' => $discount->end_date ?? null,
        ];
    }

    private function getCategories()
    {
        $category = $this->category;

        return [
            'uuid' => $category->uuid,
            'name' => $category->name,
        ];
    }

    private function getPolicies()
    {
        $policy = $this->policy;

        if (! $policy) {
            return null;
        }

        return [
            'uuid' => $policy->uuid,
            'name' => $policy->name,
        ];
    }

    private function getSections()
    {
        $sections = $this->sections;

        $sectionFiltered = [];

        foreach ($sections as $section) {
            $sectionFiltered[] = [
                'uuid' => $section->uuid,
                'name' => $section->name,
            ];
        }

        return $sectionFiltered;
    }

    private function getTags()
    {
        $tags = $this->tags;

        $tagFiltered = [];

        foreach ($tags as $tag) {
            $tagFiltered[] = $tag->name;
        }

        if (empty($tags)) {
            return '';
        }

        return implode(',', $tagFiltered);
    }

    private function getMedias()
    {
        $media = $this->medias;

        $mediaFiltered = [];

        foreach ($media as $mediaItem) {
            $mediaFiltered[] = [
                'uuid' => $mediaItem->uuid,
                'url' => $mediaItem->url,
            ];
        }

        return $mediaFiltered;
    }
}
