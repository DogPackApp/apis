<?php

namespace App\Services\Elasticsearch;

use App\Jobs\Product\SyncRecentItemSnapshotsJob;
use App\Models\Product\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductDocumentFactory
{
    public function make(Item $item): array
    {
        $tags = $item->tags->map(fn ($tag): array => [
            'id' => (int) $tag->id,
            'uuid' => $tag->uuid,
            'name' => $tag->name,
            'slug' => $tag->slug,
        ])->values();

        $variantSkus = $item->itemVariants
            ->pluck('sku')
            ->filter()
            ->unique()
            ->values();

        $attributeNames = $item->attributes
            ->pluck('name')
            ->filter()
            ->unique()
            ->values();

        $attributeOptions = $this->collectAttributeOptions($item);
        $facets = $this->collectFacetValues($item);
        $mediaUrl = $item->media_url ?? $item->medias->first()?->url;
        $stock = $this->resolveStock($item);
        $rating = (float) data_get($item->meta, 'reviews_avg_rating', 0);
        $reviewsCount = (int) data_get($item->meta, 'reviews_count', 0);
        $categoryPath = $this->categoryPath($item);

        return [
            'id' => (int) $item->id,
            'uuid' => $item->uuid,
            'slug' => $item->slug,
            'type' => (int) $item->type,
            'status' => (int) $item->status,
            'title' => $item->title,
            'description' => SyncRecentItemSnapshotsJob::cleanDescription($item->description),
            'origin' => $item->origin,
            'source_id' => $item->source_id,
            'media_url' => $mediaUrl,
            'category' => $item->category ? [
                'id' => (int) $item->category->id,
                'uuid' => $item->category->uuid,
                'name' => $item->category->name,
                'slug' => $item->category->slug,
                'path' => $categoryPath,
            ] : null,
            'category_names' => $categoryPath,
            'tags' => $tags->all(),
            'tag_names' => $tags->pluck('name')->all(),
            'store' => $item->store ? [
                'id' => (int) $item->store->id,
                'uuid' => $item->store->uuid,
                'name' => $item->store->name,
                'slug' => $item->store->slug,
                'country_id' => $item->store->country_id ? (int) $item->store->country_id : null,
                'is_verified' => (bool) $item->store->is_verified && $item->unverified === null,
            ] : null,
            'store_name' => $item->store?->name,
            'sku' => $item->price?->sku,
            'variant_skus' => $variantSkus->all(),
            'attribute_names' => $attributeNames->all(),
            'attribute_options' => $attributeOptions->all(),
            'facets' => $facets->all(),
            'price' => [
                'domestic' => $item->price?->domestic_price ? (int) $item->price->domestic_price : null,
                'global' => $item->price?->global_price ? (int) $item->price->global_price : null,
            ],
            'stock_quantity' => $stock['quantity'],
            'allow_overselling' => $stock['allow_overselling'],
            'in_stock' => $stock['in_stock'],
            'has_image' => $this->hasRealImage($mediaUrl),
            'rating_avg' => $rating,
            'reviews_count' => $reviewsCount,
            'discount_value' => $this->activeDiscountValue($item),
            ...$this->completionSuggestFields(
                $item,
                $tags,
                $this->suggestWeight($item, $rating, $reviewsCount, $stock['in_stock'])
            ),
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }

    private function collectAttributeOptions(Item $item): Collection
    {
        $directOptions = $item->attributes
            ->flatMap(fn ($attribute) => $attribute->attributeOptions->pluck('attribute_value'));

        $variantOptions = $item->itemVariants
            ->flatMap(fn ($variant) => $variant->attributesOption->pluck('attribute_value'));

        return $directOptions
            ->merge($variantOptions)
            ->filter()
            ->unique()
            ->values();
    }

    private function collectFacetValues(Item $item): Collection
    {
        return $item->activeFacets
            ->map(fn ($facetValue): array => [
                'id' => (int) $facetValue->id,
                'name' => $facetValue->name,
                'category_facet_id' => (int) $facetValue->category_facet_id,
            ])
            ->unique('id')
            ->values();
    }

    private function hasRealImage(?string $mediaUrl): bool
    {
        if ($mediaUrl === null || trim($mediaUrl) === '') {
            return false;
        }

        return ! str_contains($mediaUrl, 'empty_product');
    }

    private function resolveStock(Item $item): array
    {
        if ((int) $item->type === Item::TYPES[Item::TYPE_DIGITAL]) {
            return [
                'quantity' => 1000,
                'allow_overselling' => true,
                'in_stock' => true,
            ];
        }

        if ($item->itemVariants->isNotEmpty()) {
            $quantity = (int) $item->itemVariants->sum(fn ($variant) => (int) ($variant->stock_quantity ?? 0));
            $allowOverselling = $item->itemVariants->contains(fn ($variant) => (bool) $variant->allow_overselling);

            return [
                'quantity' => $quantity,
                'allow_overselling' => $allowOverselling,
                'in_stock' => $quantity > 0 || $allowOverselling,
            ];
        }

        $quantity = (int) ($item->price?->stock_quantity ?? 0);
        $allowOverselling = (bool) ($item->price?->allow_overselling ?? false);

        return [
            'quantity' => $quantity,
            'allow_overselling' => $allowOverselling,
            'in_stock' => $quantity > 0 || $allowOverselling,
        ];
    }

    private function categoryPath(Item $item): array
    {
        if ($item->category === null) {
            return [];
        }

        $category = $item->category;
        $path = [];

        while ($category !== null) {
            array_unshift($path, $category->name);

            if (empty($category->parent_id)) {
                break;
            }

            $category = $this->categoryById((int) $category->parent_id);
        }

        return array_values(array_filter($path));
    }

    private function categoryById(int $categoryId): ?object
    {
        return Cache::remember(
            "elasticsearch_category_path_part_{$categoryId}",
            3600,
            fn () => DB::table('categories')
                ->select('id', 'parent_id', 'name', 'slug', 'uuid')
                ->where('id', $categoryId)
                ->first()
        );
    }

    private function activeDiscountValue(Item $item): int
    {
        if ($item->discount === null) {
            return 0;
        }

        if ($item->discount->is_limited) {
            return (int) $item->discount->value;
        }

        $now = now();
        $startsOk = $item->discount->start_date === null || $item->discount->start_date <= $now;
        $endsOk = $item->discount->end_date === null || $item->discount->end_date >= $now;

        return $startsOk && $endsOk ? (int) $item->discount->value : 0;
    }

    /**
     * Typed completion payloads so suggest() can return title / store / tag separately.
     *
     * @return array<string, array{input: array<int, string>, weight: int, contexts: array{store_id: array<int, string>}}>
     */
    private function completionSuggestFields(Item $item, Collection $tags, int $weight): array
    {
        $storeId = (int) ($item->store?->id ?? $item->store_id ?? 0);
        if ($storeId <= 0) {
            return [];
        }

        $contexts = ['store_id' => [(string) $storeId]];
        $fields = [];

        $title = trim((string) ($item->title ?? ''));
        if ($title !== '') {
            $fields['suggest_title'] = [
                'input' => [$title],
                'weight' => $weight,
                'contexts' => $contexts,
            ];
        }

        $storeName = trim((string) ($item->store?->name ?? ''));
        if ($storeName !== '') {
            $fields['suggest_store'] = [
                'input' => [$storeName],
                'weight' => $weight,
                'contexts' => $contexts,
            ];
        }

        $tagNames = $tags->pluck('name')
            ->map(fn ($name): string => trim((string) $name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tagNames !== []) {
            $fields['suggest_tag'] = [
                'input' => $tagNames,
                'weight' => $weight,
                'contexts' => $contexts,
            ];
        }

        return $fields;
    }

    private function suggestWeight(Item $item, float $rating, int $reviewsCount, bool $inStock): int
    {
        $weight = 10;
        $weight += $item->store?->is_verified ? 10 : 0;
        $weight += $inStock ? 5 : 0;
        $weight += min(30, (int) round($rating * 4));
        $weight += min(30, (int) floor(log(max(1, $reviewsCount), 2)));

        return $weight;
    }
}
