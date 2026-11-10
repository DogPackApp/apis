<?php

namespace App\Repository\Product;

use App\Enums\OnboardingStep;
use App\Models\Product\Attribute;
use App\Models\Product\AttributeOption;
use App\Models\Product\Category;
use App\Models\Product\Customization;
use App\Models\Product\Discount;
use App\Models\Product\Gallery;
use App\Models\Product\Item;
use App\Models\Product\ItemMedia;
use App\Models\Product\ItemPrice;
use App\Models\Product\ItemVariant;
use App\Models\Product\Tag;
use App\Models\Product\VariantCombination;
use App\Models\Product\VaryBy;
use App\Models\Store\ItemSection;
use App\Models\Store\Section;
use App\Models\Store\Store;
use App\Services\OnboardingService;
use App\Services\Product\RemoveStopWordsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductRepository
{
    private const MAX_SLUG_LENGTH = 60;

    private ?int $lowestVariantPrice = null;

    public function __construct(private readonly OnboardingService $onboardingService) {}

    public function create(Store $store, array $data): Item
    {
        $this->lowestVariantPrice = null;

        $item = $this->createOrUpdateItem($store, $data);
        $this->attachImagesToProduct($data, $item);
        $this->createVariants($data, $item);
        $this->createVaryBy($data, $item);
        $this->attachPrice($data, $item);
        $this->attachCustomization($data, $item);
        $this->attachTags($store, $data, $item);
        $this->addItemToSection($store, $item, $data['section'] ?? null);
        $this->saveDiscount($data, $item);
        $this->verifyOnboarding($store, $item);

        return $item;
    }

    public function update(Store $store, Item $item, array $data): Item
    {
        $this->lowestVariantPrice = null;

        $item = $this->createOrUpdateItem($store, $data, $item);
        $this->attachImagesToProduct($data, $item);
        $this->syncVariants($data, $item);
        $this->createVaryBy($data, $item, update: true);
        $this->attachPrice($data, $item, update: true);
        $this->attachCustomization($data, $item, update: true);
        $this->attachTags($store, $data, $item);
        $this->addItemToSection($store, $item, $data['section'] ?? null);
        $this->saveDiscount($data, $item);
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

        $item->policy_id = $this->attachPolicy($store, $data['policy'] ?? null);

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
        if (array_key_exists('gtin', $data['price_inventory'] ?? [])) {
            $price->gtin = $data['price_inventory']['gtin'];
        }
        $price->is_global_pricing = $data['price_inventory']['is_global_pricing'] ? 1 : 0;
        $price->domestic_price = $this->lowestVariantPrice ?? $data['price_inventory']['domestic_price'] * 100;
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

    private function attachPolicy(Store $store, ?string $policyUuid): ?int
    {
        $policy = DB::table('policies')
            ->where('store_id', $store->id)
            ->where('uuid', $policyUuid)
            ->first();

        return $policy?->id;
    }

    private function cleanDescription(?string $description): ?string
    {
        return preg_replace('/<a\b[^>]*>(.*?)<\/a>/', '$1', $description);
    }

    private function addItemToSection(Store $store, Item $item, ?array $addToSection = null): void
    {
        $storeId = $store->id;

        ItemSection::query()
            ->where('item_id', $item->id)
            ->delete();

        $sections = [];
        $sectionIds = [];

        $allSection = Section::query()
            ->where('slug', 'all')
            ->where('store_id', $storeId)
            ->first();

        if (! $allSection) {
            $allSection = new Section;
            $allSection->store_id = $storeId;
            $allSection->name = 'All';
            $allSection->slug = 'all';
            $allSection->is_visible = 1;
            $allSection->order = 0;
            $allSection->save();
        }

        $sections[] = [
            'item_id' => $item->id,
            'section_id' => $allSection->id,
        ];
        $sectionIds[] = $allSection->id;

        if (! empty($addToSection)) {
            $additionalSectionIds = Section::query()
                ->where('store_id', $storeId)
                ->whereIn('uuid', $addToSection)
                ->pluck('id')
                ->toArray();

            foreach ($additionalSectionIds as $sectionId) {
                if (in_array($sectionId, $sectionIds)) {
                    continue;
                }

                $sections[] = [
                    'item_id' => $item->id,
                    'section_id' => $sectionId,
                ];
                $sectionIds[] = $sectionId;
            }
        }

        if (! empty($sections)) {
            ItemSection::insert($sections);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function attachTags(Store $store, array $data, Item $item): void
    {
        $tags = $data['category_tags']['tags'] ?? null;

        if (empty($tags)) {
            $item->tags()->sync([]);

            return;
        }

        $nameColumn = DB::getDriverName() === 'mysql' ? DB::raw('BINARY name') : 'name';

        $existingTags = Tag::query()
            ->whereIn($nameColumn, $tags)
            ->pluck('id', 'name');

        $newTags = array_diff($tags, $existingTags->keys()->all());

        if (! empty($newTags)) {
            Tag::insertOrIgnore(
                collect($newTags)->map(fn ($tag) => [
                    'uuid' => (string) Str::uuid(),
                    'name' => $tag,
                    'slug' => Str::slug($tag),
                    'seller_id' => $store->seller_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all()
            );

            $existingTags = $existingTags->merge(
                Tag::query()->whereIn($nameColumn, $newTags)->pluck('id', 'name')
            );
        }

        $item->tags()->sync($existingTags->values()->all());
    }

    private function createVariants(array $data, Item $item): void
    {
        if (! isset($data['item_variations'])) {
            return;
        }

        $variants = $data['item_variations']['variants'];
        $attributeIds = [];

        foreach ($variants as $variant) {
            $attribute = new Attribute;
            $attribute->name = $variant['name'];
            $attribute->item_id = $item->id;
            $attribute->save();

            $attributeIds[] = $attribute->id;

            $optionsData = collect($variant['options'])
                ->map(fn ($option) => [
                    'uuid' => (string) Str::uuid(),
                    'attribute_id' => $attribute->id,
                    'attribute_value' => $option,
                ])
                ->unique('attribute_value')
                ->values()
                ->all();

            AttributeOption::upsert($optionsData, ['attribute_id', 'attribute_value']);
        }

        $this->createCombinations($data, $item, $attributeIds);
    }

    /**
     * @param  array<int, int>  $attributeIds
     */
    private function createCombinations(array $data, Item $item, array $attributeIds): void
    {
        $combinations = $data['item_variations']['combination'];
        $itemVariantCount = ItemVariant::query()->where('item_id', $item->id)->count();

        foreach ($combinations as $combination) {
            $optionIds = AttributeOption::query()
                ->whereIn('attribute_value', $this->fetchAttributeValues($combination))
                ->whereIn('attribute_id', $attributeIds)
                ->pluck('id')
                ->all();

            $itemVariant = new ItemVariant;
            $itemVariant->domestic_price = isset($combination['domestic_price']) ? $combination['domestic_price'] * 100 : null;
            $itemVariant->global_price = isset($combination['global_price']) ? $combination['global_price'] * 100 : null;
            $itemVariant->sku = $combination['sku'] ?? null;
            if (array_key_exists('gtin', $combination)) {
                $itemVariant->gtin = $combination['gtin'];
            }
            $itemVariant->stock_quantity = $combination['quantity'] ?? 0;
            $itemVariant->weight = $combination['weight'] ?? null;
            $itemVariant->order = $itemVariantCount++;
            $itemVariant->item_id = $item->id;
            $itemVariant->is_visible = $combination['is_visible'] ?? true;
            $itemVariant->allow_overselling = $combination['allow_overselling'] ?? true;
            $itemVariant->save();

            $this->updateLowestPricedCombinationValue($itemVariant->domestic_price);

            if (! empty($combination['image'])) {
                $this->attachVariantImageToProduct([$combination['image']], $item, $itemVariant->id);
            }

            $variantCombination = [];

            foreach ($optionIds as $optionId) {
                $variantCombination[] = [
                    'uuid' => (string) Str::uuid(),
                    'attribute_option_id' => $optionId,
                    'item_variant_id' => $itemVariant->id,
                ];
            }

            VariantCombination::insert($variantCombination);
        }
    }

    /**
     * @throws \Exception
     */
    private function syncVariants(array $data, Item $item): void
    {
        try {
            if (! isset($data['item_variations'])) {
                return;
            }

            $variants = $data['item_variations']['variants'];
            $attributeIds = [];
            $allAttributes = Attribute::query()
                ->where('item_id', $item->id)
                ->pluck('name')
                ->all();
            $incomingNames = collect($variants)->pluck('name')->all();
            $toDelete = array_diff($allAttributes, $incomingNames);

            foreach ($toDelete as $deleteVariant) {
                $attribute = Attribute::query()
                    ->where('name', $deleteVariant)
                    ->where('item_id', $item->id)
                    ->first();

                $attributeOptionIds = AttributeOption::query()
                    ->where('attribute_id', $attribute->id)
                    ->pluck('id')
                    ->all();

                VariantCombination::query()
                    ->whereIn('attribute_option_id', $attributeOptionIds)
                    ->delete();

                AttributeOption::query()
                    ->where('attribute_id', $attribute->id)
                    ->delete();

                Attribute::query()
                    ->where('id', $attribute->id)
                    ->delete();
            }

            foreach ($variants as $variant) {
                $attribute = Attribute::query()
                    ->where('name', $variant['name'])
                    ->where('item_id', $item->id)
                    ->first();

                if (! $attribute) {
                    $attribute = new Attribute;
                    $attribute->name = $variant['name'];
                    $attribute->item_id = $item->id;
                    $attribute->uuid = (string) Str::uuid();
                    $attribute->save();
                }

                $attributeIds[] = $attribute->id;

                $optionsData = collect($variant['options'])
                    ->map(fn ($option) => [
                        'uuid' => (string) Str::uuid(),
                        'attribute_id' => $attribute->id,
                        'attribute_value' => $option,
                    ])
                    ->unique('attribute_value')
                    ->values()
                    ->all();

                $existingOptions = AttributeOption::query()
                    ->where('attribute_id', $attribute->id)
                    ->pluck('attribute_value')
                    ->all();
                $incomingOptions = array_column($optionsData, 'attribute_value');
                $attributeOptionDelete = array_diff($existingOptions, $incomingOptions);
                $attributeOptionsToInsert = array_diff($incomingOptions, $existingOptions);

                if (! empty($attributeOptionDelete)) {
                    foreach ($attributeOptionDelete as $attributeOption) {
                        $option = AttributeOption::query()
                            ->where('attribute_id', $attribute->id)
                            ->where('attribute_value', $attributeOption)
                            ->first();

                        if ($option) {
                            $itemVariantIds = VariantCombination::query()
                                ->where('attribute_option_id', $option->id)
                                ->pluck('item_variant_id')
                                ->all();

                            VariantCombination::query()
                                ->whereIn('item_variant_id', $itemVariantIds)
                                ->delete();

                            ItemMedia::query()
                                ->whereIn('item_variant_id', $itemVariantIds)
                                ->delete();

                            ItemVariant::query()
                                ->whereIn('id', $itemVariantIds)
                                ->delete();

                            $option->delete();
                        }
                    }
                }

                if (! empty($attributeOptionsToInsert)) {
                    $optionsToInsert = array_filter($optionsData, function (array $option) use ($attributeOptionsToInsert): bool {
                        return in_array($option['attribute_value'], $attributeOptionsToInsert);
                    });

                    AttributeOption::insert($optionsToInsert);
                }
            }

            $this->updateCombination($data['item_variations']['combination'], $item, $attributeIds);
        } catch (\Exception $exception) {
            Log::info($exception);

            throw $exception;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $combinations
     * @param  array<int, int>  $attributeIds
     */
    private function updateCombination(array $combinations, Item $item, array $attributeIds): void
    {
        $existingVariants = ItemVariant::query()
            ->where('item_id', $item->id)
            ->get();
        $itemVariantCount = $existingVariants->count();
        $variantIds = [];

        foreach ($combinations as $combination) {
            $variantQuery = ItemVariant::query()->where('item_id', $item->id);

            if (isset($combination['uuid'])) {
                $variantQuery->where('uuid', $combination['uuid']);
            } else {
                $variantQuery = null;
            }

            $variant = $variantQuery ? $variantQuery->first() : null;

            if (! $variant) {
                $variant = new ItemVariant;
                $itemVariantCount++;
            }

            $variant->domestic_price = isset($combination['domestic_price']) ? $combination['domestic_price'] * 100 : null;
            $variant->global_price = isset($combination['global_price']) ? $combination['global_price'] * 100 : null;
            $variant->sku = $combination['sku'] ?? null;
            if (array_key_exists('gtin', $combination)) {
                $variant->gtin = $combination['gtin'];
            }
            $variant->stock_quantity = $combination['quantity'] ?? 0;
            $variant->weight = isset($combination['weight']) && $combination['weight'] > 0
                ? $combination['weight']
                : $variant->weight ?? null;
            $variant->order = $variant?->order ?? $itemVariantCount++;
            $variant->item_id = $item->id;
            $variant->is_visible = $combination['is_visible'] ?? true;
            $variant->allow_overselling = $combination['allow_overselling'] ?? 0;
            $variant->source_id = $combination['shopify_id'] ?? $variant->source_id;
            $variant->save();

            $this->updateLowestPricedCombinationValue($variant->domestic_price);
            $variantIds[] = $variant->id;

            $optionIds = AttributeOption::query()
                ->whereIn('attribute_value', $this->fetchAttributeValues($combination))
                ->whereIn('attribute_id', $attributeIds)
                ->pluck('id')
                ->all();

            foreach ($optionIds as $optionId) {
                $exists = VariantCombination::query()
                    ->where('attribute_option_id', $optionId)
                    ->where('item_variant_id', $variant->id)
                    ->exists();

                if (! $exists) {
                    VariantCombination::query()->create([
                        'uuid' => (string) Str::uuid(),
                        'attribute_option_id' => $optionId,
                        'item_variant_id' => $variant->id,
                    ]);
                }
            }

            if (! empty($combination['image'])) {
                $this->attachVariantImageToProduct([$combination['image']], $item, $variant->id);
            }
        }

        $variantToDelete = array_diff($existingVariants->pluck('id')->all(), $variantIds);

        if (! empty($variantToDelete)) {
            VariantCombination::query()->whereIn('item_variant_id', $variantToDelete)->delete();
            ItemMedia::query()->whereIn('item_variant_id', $variantToDelete)->delete();
            ItemVariant::query()
                ->where('item_id', $item->id)
                ->whereIn('id', $variantToDelete)
                ->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $combination
     * @return array<int, string>
     */
    private function fetchAttributeValues(array $combination): array
    {
        $attributeValue = [];
        $attributeValue[] = $combination['variants'][0]['value'];

        if (isset($combination['variants'][1]['value'])) {
            $attributeValue[] = $combination['variants'][1]['value'];
        }

        if (isset($combination['variants'][2]['value'])) {
            $attributeValue[] = $combination['variants'][2]['value'];
        }

        return $attributeValue;
    }

    private function createVaryBy(array $data, Item $item, bool $update = false): void
    {
        if (! isset($data['item_variations']) && ! isset($data['item_variations']['vary_by'])) {
            return;
        }

        $varyBy = $update ? ($item->varyBy ?? new VaryBy) : new VaryBy;
        $varyByData = $data['item_variations']['vary_by'];

        $varyBy->item_id = $item->id;
        $varyBy->sku = $varyByData['sku'] ?? 0;
        $varyBy->weight = $varyByData['weight'] ?? 0;
        $varyBy->quantity = $varyByData['quantity'] ?? 0;
        $varyBy->price = $varyByData['price'] ?? 0;
        $varyBy->save();
    }

    private function updateLowestPricedCombinationValue(mixed $price): void
    {
        if (! $price) {
            return;
        }

        if ($this->lowestVariantPrice === null || $price < $this->lowestVariantPrice) {
            $this->lowestVariantPrice = $price;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function attachImagesToProduct(array $data, Item $item): void
    {
        if (isset($data['about']['images'])) {
            $this->attachImageToProduct($data['about']['images'], $item);
        }
    }

    /**
     * @param  array<int, string>  $images
     */
    private function attachImageToProduct(array $images, Item $item): void
    {
        $gallery = Gallery::query()
            ->select('id', 'uuid', 'type', 'url')
            ->where('store_id', $item->store_id)
            ->whereIn('uuid', $images)
            ->get()
            ->keyBy('uuid')
            ->toArray();

        ItemMedia::query()
            ->whereNull('item_variant_id')
            ->where('item_id', $item->id)
            ->delete();

        $itemMedias = [];
        $attachedCoverImageToProduct = false;

        foreach ($images as $index => $uuid) {
            if (! isset($gallery[$uuid])) {
                continue;
            }

            $itemMedias[] = [
                'uuid' => (string) Str::uuid(),
                'item_id' => $item->id,
                'gallery_id' => $gallery[$uuid]['id'],
                'order' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (! $attachedCoverImageToProduct && str_starts_with($gallery[$uuid]['type'], 'image')) {
                $attachedCoverImageToProduct = true;
                $item->media_url = $gallery[$uuid]['url'];
                $item->save();
            }
        }

        if (! $attachedCoverImageToProduct) {
            $item->media_url = 'uploads/default-images/empty_product.webp';
            $item->save();
        }

        if ($itemMedias !== []) {
            ItemMedia::query()->insert($itemMedias);
        }
    }

    /**
     * @param  array<int, string>  $images
     */
    private function attachVariantImageToProduct(array $images, Item $item, int $variantId): void
    {
        $galleryIds = Gallery::query()
            ->where('store_id', $item->store_id)
            ->whereIn('uuid', $images)
            ->pluck('id')
            ->all();

        ItemMedia::query()
            ->where('item_variant_id', $variantId)
            ->where('item_id', $item->id)
            ->delete();

        $itemMedias = [];

        foreach ($galleryIds as $galleryId) {
            $itemMedias[] = [
                'uuid' => (string) Str::uuid(),
                'item_id' => $item->id,
                'item_variant_id' => $variantId,
                'gallery_id' => $galleryId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($itemMedias !== []) {
            ItemMedia::query()->insert($itemMedias);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveDiscount(array $data, Item $item): void
    {
        if (! isset($data['discount'])) {
            Discount::query()
                ->where('item_id', $item->id)
                ->delete();

            return;
        }

        $discount = $item->discount ?? new Discount;
        $discount->item_id = $item->id;
        $discount->value = $data['discount']['value'] * 100;
        $discount->is_limited = isset($data['discount']['is_limited']) ? ($data['discount']['is_limited'] ? 1 : 0) : 0;

        if ($discount->is_limited === 1) {
            $discount->start_date = null;
            $discount->end_date = null;
        } else {
            $discount->end_date = $data['discount']['end_date'] ?? null;
            $discount->start_date = $data['discount']['start_date'] ?? null;
        }

        $discount->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function attachCustomization(array $data, Item $item, bool $update = false): void
    {
        $isEnabled = isset($data['about']['customizations']['enabled']) ?? false;

        if (! $isEnabled) {
            Customization::query()
                ->where('item_id', $item->id)
                ->delete();

            return;
        }

        $customization = $update
            ? ($item->customization ?? new Customization)
            : new Customization;

        $customization->item_id = $item->id;
        $customization->description_enabled = $data['about']['customizations']['description_enabled'] ?? null;
        $customization->description = $customization->description_enabled ? $data['about']['customizations']['description'] ?? null : null;
        $customization->limit = $customization->description_enabled ? $data['about']['customizations']['limit'] ?? null : null;
        $customization->attach_file = $data['about']['customizations']['attach_file'] ?? null;
        $customization->save();
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
