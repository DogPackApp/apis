<?php

namespace App\Repository\Product;

use App\Enums\OnboardingStep;
use App\Models\Product\Category;
use App\Models\Product\Item;
use App\Models\Product\ItemPrice;
use App\Models\Store\Store;
use App\Services\OnboardingService;
use App\Services\Product\RemoveStopWordsService;
use Illuminate\Support\Str;

class ProductRepository
{
    private const MAX_SLUG_LENGTH = 60;

    public function __construct(private readonly OnboardingService $onboardingService) {}

    public function create(Store $store, array $data): Item
    {
        $item = $this->createOrUpdateItem($store, $data);
        $this->attachPrice($data, $item);
        $this->verifyOnboarding($store, $item);

        return $item;
    }

    public function update(Store $store, Item $item, array $data): Item
    {
        $item = $this->createOrUpdateItem($store, $data, $item);
        $this->attachPrice($data, $item, update: true);
        $this->verifyOnboarding($store, $item);

        return $item;
    }

    private function createOrUpdateItem(Store $store, array $data, ?Item $item = null): Item
    {
        $isCreating = $item === null;

        if ($isCreating) {
            $item = new Item;
        }

        $item->title = $data['about']['title'];
        $item->type = Item::TYPES[$data['about']['type']];
        $item->origin = $data['about']['origin'];
        $item->store_id = $store->id;
        $item->description = $this->cleanDescription($data['about']['description']);

        if ($isCreating) {
            $item->slug = self::generateProductSlug($item->title);
        }

        // No policies table exists yet (Policy phase not built) — always null,
        // instead of replicating backend-api's DB::table('policies') lookup.
        $item->policy_id = null;

        $categoryId = $item->category_id
            ?? ($item->type === Item::TYPES[Item::TYPE_PHYSICAL] ? 278 : 286);

        if (isset($data['category_tags']['category']) && $data['category_tags']['category'] !== null) {
            $category = Category::query()->where('uuid', $data['category_tags']['category'])->first();

            if ($category) {
                $categoryId = $category->id;
            }
        }

        $item->category_id = $categoryId;

        $itemStatus = isset($data['status'])
            ? Item::STATUS[$data['status']]
            : Item::STATUS[Item::STATUS_ACTIVE];

        if ($itemStatus === Item::STATUS[Item::STATUS_ARCHIVED] || $itemStatus === Item::STATUS[Item::STATUS_DRAFT]) {
            $item->manually_deactivated = true;
        }

        if ($itemStatus === Item::STATUS[Item::STATUS_ACTIVE]) {
            $item->manually_deactivated = false;
        }

        $item->status = $itemStatus;
        $item->save();

        return $item;
    }

    private function attachPrice(array $data, Item $item, bool $update = false): ItemPrice
    {
        $price = $update ? ($item->price ?? new ItemPrice) : new ItemPrice;

        $isDigital = $item->type === Item::TYPES[Item::TYPE_DIGITAL];

        $price->item_id = $item->id;
        $price->sku = $data['price_inventory']['sku'] ?? null;
        $price->is_global_pricing = $data['price_inventory']['is_global_pricing'] ? 1 : 0;
        $price->domestic_price = $data['price_inventory']['domestic_price'] * 100;
        $price->global_price = $data['price_inventory']['global_price']
            ? $data['price_inventory']['global_price'] * 100
            : null;
        $price->stock_quantity = $isDigital ? null : $data['price_inventory']['stock_quantity'];
        $price->weight = $isDigital ? null : $data['price_inventory']['weight'] * 100;
        $price->unit = isset($data['price_inventory']['unit']) ? Item::ITEM_WEIGHT[$data['price_inventory']['unit']] : null;
        $price->allow_overselling = $data['price_inventory']['allow_overselling'] ?? 0;
        $price->save();

        return $price;
    }

    private function verifyOnboarding(Store $store, Item $item): void
    {
        if ($item->status !== Item::STATUS[Item::STATUS_ACTIVE]) {
            return;
        }

        if (Item::query()->where('store_id', $store->id)->count() > 1) {
            return;
        }

        $this->onboardingService->complete($store->seller, OnboardingStep::Product, $store);
    }

    private function cleanDescription(?string $description): ?string
    {
        return preg_replace('/<a\b[^>]*>(.*?)<\/a>/', '$1', $description);
    }

    public static function generateProductSlug(string $title): string
    {
        $fullSlug = Str::slug($title);

        $filtered = RemoveStopWordsService::removeStopWords(
            str_replace('-', ' ', $fullSlug)
        );

        $words = array_filter(array_unique(explode(' ', $filtered)));

        $slug = '';

        if (empty($words)) {
            $words = explode('-', $fullSlug);
        }

        foreach ($words as $word) {
            $newSlug = $slug === '' ? $word : $slug.'-'.$word;

            if (strlen($newSlug) > self::MAX_SLUG_LENGTH) {
                break;
            }

            $slug = $newSlug;
        }

        return $slug;
    }
}
