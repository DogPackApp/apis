<?php

namespace App\Services\SubscribeSave;

use App\Models\Product\Item;
use App\Models\SubscribeSave\ItemSubscribeSaveSetting;
use App\Models\SubscribeSave\SubscribeSaveSetting;
use Illuminate\Support\Facades\Cache;

class SubscribeSaveEligibilityService
{
    public const CACHE_KEY = 'subscribe_save:platform_settings';

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
}
