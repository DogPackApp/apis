<?php

namespace App\Services\SubscribeSave;

use App\Models\Product\Item;
use App\Models\SubscribeSave\ItemSubscribeSaveSetting;
use App\Models\SubscribeSave\SubscribeSaveSetting;
use App\Services\Order\CountryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SubscribeSaveEligibilityService
{
    public const CACHE_KEY = 'subscribe_save:platform_settings';

    public const STORE_CACHE_KEY_PREFIX = 'subscribe_save:store:';

    public const ITEM_CACHE_KEY_PREFIX = 'subscribe_save:item:';

    public const CACHE_TTL = 86400;

    public const DEFAULT_ALLOWED_PERCENTS = [5, 10, 15, 20, 25, 30, 35, 40, 45, 50];

    public const DEFAULT_FREQUENCIES_DAYS = [7, 14, 28];

    public const REASON_STORE_DISABLED = 'store_disabled';

    public const REASON_DIGITAL = 'digital';

    public const REASON_ADMIN_FORCE_OFF = 'admin_force_off';

    public const REASON_CATEGORY_DISABLED = 'category_disabled';

    public const REASON_SELLER_DISABLED = 'seller_disabled';

    public const REASON_NO_SELLER_DISCOUNT = 'no_seller_discount';

    private ?array $platformSettings = null;

    /**
     * @return array<int, int>
     */
    public function allowedSellerPercents(): array
    {
        return $this->platformSettings()['allowed_seller_percents'];
    }

    /**
     * Seller-panel label vs customer-available Subscribe & Save for one item.
     * Category flag is only the product's own category (children are not inherited).
     *
     * @return array{
     *     store_disabled: bool,
     *     seller_disabled: bool,
     *     product_discount_percent: int|null,
     *     global_discount_percent: int|null,
     *     effective_discount_percent: int|null,
     *     dogpack_first_order_percent: int,
     *     allowed_percents: array<int, int>,
     *     show_label: bool,
     *     available_to_customers: bool,
     *     reason: string|null
     * }
     */
    public function forItem(Item $item): array
    {
        $item->loadMissing(['category', 'subscribeSaveSetting', 'store.settings']);

        $platform = $this->platformSettings();
        $setting = $item->subscribeSaveSetting;
        $storeSettings = $item->store?->settings;

        $productDiscount = $setting?->discount_percent;
        $globalDiscount = $storeSettings?->subscribe_save_discount_percent;
        $effectiveDiscount = $productDiscount ?? $globalDiscount;

        $dogpackFirstOrder = $storeSettings?->dogpack_subscribe_save_first_order_percent
            ?? $platform['dogpack_first_order_percent'];

        $base = [
            'store_disabled' => (bool) ($storeSettings?->subscribe_save_disabled),
            'seller_disabled' => (bool) ($setting?->seller_disabled),
            'product_discount_percent' => $productDiscount,
            'global_discount_percent' => $globalDiscount,
            'effective_discount_percent' => $effectiveDiscount,
            'dogpack_first_order_percent' => $dogpackFirstOrder,
            'allowed_percents' => $platform['allowed_seller_percents'],
        ];

        if ((int) $item->type === Item::TYPES[Item::TYPE_DIGITAL]) {
            return $this->result($base, false, false, self::REASON_DIGITAL);
        }

        if ($storeSettings?->subscribe_save_disabled) {
            return $this->result($base, false, false, self::REASON_STORE_DISABLED);
        }

        $adminOverride = $setting?->admin_override;
        if ($adminOverride === ItemSubscribeSaveSetting::ADMIN_OVERRIDE_FORCE_OFF) {
            return $this->result($base, false, false, self::REASON_ADMIN_FORCE_OFF);
        }

        $categoryEnabled = (bool) ($item->category?->subscribe_save_enabled);
        $inProgram = $adminOverride === ItemSubscribeSaveSetting::ADMIN_OVERRIDE_FORCE_ON || $categoryEnabled;

        if (! $inProgram) {
            return $this->result($base, false, false, self::REASON_CATEGORY_DISABLED);
        }

        if ($setting?->seller_disabled) {
            return $this->result($base, true, false, self::REASON_SELLER_DISABLED);
        }

        if ($effectiveDiscount === null) {
            return $this->result($base, true, false, self::REASON_NO_SELLER_DISCOUNT);
        }

        return $this->result($base, true, true, null);
    }

    /**
     * @param  array{
     *     store_disabled: bool,
     *     seller_disabled: bool,
     *     product_discount_percent: int|null,
     *     global_discount_percent: int|null,
     *     effective_discount_percent: int|null,
     *     dogpack_first_order_percent: int,
     *     allowed_percents: array<int, int>
     * }  $base
     * @return array{
     *     store_disabled: bool,
     *     seller_disabled: bool,
     *     product_discount_percent: int|null,
     *     global_discount_percent: int|null,
     *     effective_discount_percent: int|null,
     *     dogpack_first_order_percent: int,
     *     allowed_percents: array<int, int>,
     *     show_label: bool,
     *     available_to_customers: bool,
     *     reason: string|null
     * }
     */
    private function result(array $base, bool $showLabel, bool $availableToCustomers, ?string $reason): array
    {
        return array_merge($base, [
            'show_label' => $showLabel,
            'available_to_customers' => $availableToCustomers,
            'reason' => $reason,
        ]);
    }

    /**
     * @return array{
     *     dogpack_first_order_percent: int,
     *     allowed_seller_percents: array<int, int>,
     *     allowed_frequencies_days: array<int, int>,
     *     threshold_percent: array<string, float>,
     *     max_discount_amount: array<string, float>
     * }
     */
    private function platformSettings(): array
    {
        if ($this->platformSettings !== null) {
            return $this->platformSettings;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && array_key_exists('max_discount_amount', $cached)) {
            $this->platformSettings = $cached;

            return $this->platformSettings;
        }

        if ($cached !== null) {
            Cache::forget(self::CACHE_KEY);
        }

        $this->platformSettings = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $row = SubscribeSaveSetting::query()->first();

            $percents = $row?->allowed_seller_percents;
            if (! is_array($percents) || $percents === []) {
                $percents = self::DEFAULT_ALLOWED_PERCENTS;
            }

            $frequencies = $row?->allowed_frequencies_days;
            if (! is_array($frequencies) || $frequencies === []) {
                $frequencies = self::DEFAULT_FREQUENCIES_DAYS;
            }

            return [
                'dogpack_first_order_percent' => (int) ($row?->dogpack_first_order_percent ?? 0),
                'allowed_seller_percents' => array_values(array_map('intval', $percents)),
                'allowed_frequencies_days' => array_values(array_map('intval', $frequencies)),
                'threshold_percent' => $this->byCountry($row?->threshold_percent),
                'max_discount_amount' => $this->byCountry($row?->max_discount_amount),
            ];
        });

        return $this->platformSettings;
    }

    /**
     * {"CA": 30} keyed by upper-case country code; anything not numeric is dropped.
     *
     * @return array<string, float>
     */
    private function byCountry(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $clean = [];
        foreach ($values as $code => $value) {
            if (is_numeric($value)) {
                $clean[strtoupper((string) $code)] = (float) $value;
            }
        }

        return $clean;
    }

    /**
     * @return array{
     *     seller_discount_percent: int,
     *     dogpack_first_order_percent: int,
     *     first_order_discount_percent: int,
     *     renewal_discount_percent: int,
     *     frequencies_days: array<int, int>
     * }|null
     */
    public function offerFor(int $itemId, int $storeId, bool $available): ?array
    {
        if (! $available) {
            return null;
        }

        $store = $this->storeSubscribeSave($storeId);
        $productPercent = $this->itemSubscribeSaveDiscount($itemId);

        $sellerPercent = $productPercent ?? $store['discount_percent'];
        if ($sellerPercent === null) {
            return null;
        }

        $sellerPercent = (int) $sellerPercent;
        $dogpackPercent = (int) ($store['dogpack_first_order_percent']
            ?? $this->platformSettings()['dogpack_first_order_percent']);

        return [
            'seller_discount_percent' => $sellerPercent,
            'dogpack_first_order_percent' => $dogpackPercent,
            'first_order_discount_percent' => $sellerPercent + $dogpackPercent,
            'renewal_discount_percent' => $sellerPercent,
            'frequencies_days' => $this->platformSettings()['allowed_frequencies_days'],
        ];
    }

    /**
     * @param  array<int, int>  $itemIds
     * @param  array<int, int>  $storeIds
     */
    public function warmOfferCaches(array $itemIds, array $storeIds): void
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
        $storeIds = array_values(array_unique(array_map('intval', $storeIds)));

        $missingItemIds = $this->uncachedIds($itemIds, self::ITEM_CACHE_KEY_PREFIX);

        if ($missingItemIds !== []) {
            $percents = DB::connection('mysql_reader')
                ->table('item_subscribe_save_settings')
                ->whereIn('item_id', $missingItemIds)
                ->pluck('discount_percent', 'item_id');

            foreach ($missingItemIds as $itemId) {
                $this->putItemSubscribeSaveCache($itemId, $percents[$itemId] ?? null);
            }
        }

        $missingStoreIds = $this->uncachedIds($storeIds, self::STORE_CACHE_KEY_PREFIX);

        if ($missingStoreIds !== []) {
            $rows = DB::connection('mysql_reader')
                ->table('store_settings')
                ->whereIn('store_id', $missingStoreIds)
                ->get([
                    'store_id',
                    'subscribe_save_discount_percent',
                    'dogpack_subscribe_save_first_order_percent',
                ])
                ->keyBy('store_id');

            foreach ($missingStoreIds as $storeId) {
                $row = $rows[$storeId] ?? null;
                $this->putStoreSubscribeSaveCache(
                    $storeId,
                    $row?->subscribe_save_discount_percent,
                    $row?->dogpack_subscribe_save_first_order_percent
                );
            }
        }
    }

    /**
     * @return array{threshold_percent: int, max_discount_amount: float}|null
     */
    public function firstOrderCap(array $offer, ?int $countryId): ?array
    {
        if (! empty($offer['is_renewal']) || ! $countryId) {
            return null;
        }

        $country = (new CountryService)->getCountryById($countryId);
        $code = strtoupper((string) ($country['short_name'] ?? ''));
        $settings = $this->platformSettings();
        $threshold = $settings['threshold_percent'][$code] ?? null;
        $max = $settings['max_discount_amount'][$code] ?? null;

        if ($threshold === null || $max === null || $max <= 0
            || (float) $offer['first_order_discount_percent'] < $threshold) {
            return null;
        }

        return ['threshold_percent' => (int) $threshold, 'max_discount_amount' => round($max, 2)];
    }

    /**
     * @return array{discount_percent: int|null, dogpack_first_order_percent: int|null}
     */
    public function storeSubscribeSave(int $storeId): array
    {
        $key = self::STORE_CACHE_KEY_PREFIX.$storeId;
        $cached = Cache::get($key);
        if (is_array($cached)
            && array_key_exists('discount_percent', $cached)
            && array_key_exists('dogpack_first_order_percent', $cached)
        ) {
            return $cached;
        }

        $row = DB::connection('mysql_reader')
            ->table('store_settings')
            ->where('store_id', $storeId)
            ->first([
                'subscribe_save_discount_percent',
                'dogpack_subscribe_save_first_order_percent',
            ]);

        return $this->putStoreSubscribeSaveCache(
            $storeId,
            $row?->subscribe_save_discount_percent,
            $row?->dogpack_subscribe_save_first_order_percent
        );
    }

    /**
     * @return array{discount_percent: int|null, dogpack_first_order_percent: int|null}
     */
    public function putStoreSubscribeSaveCache(int $storeId, mixed $discountPercent, mixed $dogpackPercent): array
    {
        $payload = [
            'discount_percent' => $discountPercent === null ? null : (int) $discountPercent,
            'dogpack_first_order_percent' => $dogpackPercent === null ? null : (int) $dogpackPercent,
        ];

        Cache::put(self::STORE_CACHE_KEY_PREFIX.$storeId, $payload, self::CACHE_TTL);

        return $payload;
    }

    public function itemSubscribeSaveDiscount(int $itemId): ?int
    {
        $key = self::ITEM_CACHE_KEY_PREFIX.$itemId;
        $cached = Cache::get($key);
        if (is_array($cached) && array_key_exists('discount_percent', $cached)) {
            return $cached['discount_percent'];
        }

        $value = DB::connection('mysql_reader')
            ->table('item_subscribe_save_settings')
            ->where('item_id', $itemId)
            ->value('discount_percent');

        return $this->putItemSubscribeSaveCache($itemId, $value)['discount_percent'];
    }

    /**
     * @return array{discount_percent: int|null}
     */
    public function putItemSubscribeSaveCache(int $itemId, mixed $discountPercent): array
    {
        $payload = [
            'discount_percent' => $discountPercent === null ? null : (int) $discountPercent,
        ];

        Cache::put(self::ITEM_CACHE_KEY_PREFIX.$itemId, $payload, self::CACHE_TTL);

        return $payload;
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function uncachedIds(array $ids, string $prefix): array
    {
        if ($ids === []) {
            return [];
        }

        $keys = array_map(static fn (int $id): string => $prefix.$id, $ids);
        $cached = Cache::many($keys);

        $missing = [];
        foreach ($ids as $index => $id) {
            if (! is_array($cached[$keys[$index]] ?? null)) {
                $missing[] = $id;
            }
        }

        return $missing;
    }
}
