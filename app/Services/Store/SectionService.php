<?php

namespace App\Services\Store;

use App\Models\Product\Item;
use App\Models\Store\Section;
use App\Models\Store\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SectionService
{
    public function fetchSections($store): Collection|array
    {
        return Section::query()
            ->where('is_visible', 1)
            ->whereHas('items', function ($query) {
                $query->where('status', Item::STATUS[Item::STATUS_ACTIVE]);
            })
            ->withCount(['items' => function ($query) {
                $query->where('status', Item::STATUS[Item::STATUS_ACTIVE]);
            }])
            ->where('store_id', $store->id)
            ->orderBy('order')
            ->get();
    }

    public function findByUUID($uuid): Section|Model
    {
        return Section::query()
            ->where('uuid', $uuid)
            ->where('store_id', resolve(Store::class)->id)
            ->first();
    }

    public function findByUUIDs($uuid): array|Collection
    {
        return Section::query()
            ->whereIn('uuid', $uuid)
            ->where('store_id', resolve(Store::class)->id)
            ->get();
    }

    public function fetchItemsBySection(Section $section): array
    {
        return $section->load('items')?->items?->pluck('uuid')?->toArray();
    }

    public function moveToDefault(array $itemIds): array
    {
        $defaultSection = Store::_defaultStoreSection();

        return $this->updateItemsToSection($itemIds, $defaultSection);
    }

    public function updateItemsToSection(array $itemIds, $section): array
    {
        Cache::forget("store_sections_{$section->store_id}");

        $maxOrder = $section->items()
            ->where('item_section.section_id', $section->id)
            ->max('item_section.order') ?? -1;

        $pivotData = [];
        foreach ($itemIds as $offset => $itemId) {
            $pivotData[$itemId] = [
                'order' => $maxOrder + $offset + 1,
            ];
        }

        return $section->items()->syncWithoutDetaching($pivotData);
    }

    public function removeItemsFromSection(array $itemIds, $section): int
    {
        Cache::forget("store_sections_{$section->store_id}");

        return $section->items()->detach($itemIds);
    }
}
