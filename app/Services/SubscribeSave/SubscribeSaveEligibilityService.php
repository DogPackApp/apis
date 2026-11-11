<?php

namespace App\Services\SubscribeSave;

use App\Models\SubscribeSave\SubscribeSaveSetting;
use Illuminate\Support\Facades\Cache;

class SubscribeSaveEligibilityService
{
    public const CACHE_KEY = 'subscribe_save:platform_settings';

    public const CACHE_TTL = 86400;

    public const DEFAULT_ALLOWED_PERCENTS = [5, 10, 15, 20, 25, 30, 35, 40, 45, 50];

    public const DEFAULT_FREQUENCIES_DAYS = [7, 14, 28];

    private ?array $platformSettings = null;

    /**
     * @return array<int, int>
     */
    public function allowedSellerPercents(): array
    {
        return $this->platformSettings()['allowed_seller_percents'];
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
