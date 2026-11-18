<?php

namespace App\Services\Store;

use App\Models\Product\Item;
use App\Models\Store\ShippingZoneRate;
use App\Models\Store\Store;
use App\Services\Order\CountryService;
use App\Services\Order\CurrencyService;
use Exception;
use Illuminate\Support\Facades\Cache;

class StoreFreeShippingService
{
    protected $currencyService;

    protected $countryService;

    const CACHE_TTL = 60 * 60 * 24;

    const FREE_SHIPPING_CACHE_KEY_PREFIX = 'store_free_shipping_ids:';

    const BEST_SHIPPING_RATE_CACHE_KEY_PREFIX = 'store_best_shipping_rate:';

    const BEST_PRODUCT_SHIPPING_RATE_CACHE_KEY_PREFIX = 'product_best_shipping_rate:';

    const FREE_SHIPPING_ITEM_CACHE_KEY_PREFIX = 'store_free_shipping_item_ids:';

    public function __construct(CurrencyService $currencyService, CountryService $countryService)
    {
        $this->currencyService = $currencyService;
        $this->countryService = $countryService;
    }

    private function computeAndAggregateShippingDataForStoreByCountry(int $storeId, int $countryId): array
    {
        $store = Store::on('mysql_reader')
            ->select(['id', 'country_id'])
            ->with([
                'zones' => function ($query) use ($countryId) {
                    $query->select(['id', 'store_id'])
                        ->whereHas('shippingZoneCountry', function ($q) use ($countryId) {
                            $q->where('country_id', $countryId);
                        })
                        ->with([
                            'shippingZoneCountry' => function ($q) use ($countryId) {
                                $q->select(['id', 'shipping_zone_id', 'country_id'])
                                    ->where('country_id', $countryId);
                            },
                            'shippingZoneRates' => function ($q) {
                                $q->select([
                                    'id',
                                    'shipping_zone_id',
                                    'price',
                                    'shipping_type',
                                    'low_value',
                                    'high_value',
                                ])
                                    ->where('shipping_type', 1)
                                    ->where(function ($q) {
                                        $q->where('condition_type', ShippingZoneRate::ZONE_CONDITION_TYPE[ShippingZoneRate::ZONE_CONDITION_PRICE])
                                            ->orWhereNull('condition_type');
                                    });
                            },
                        ]);
                },
                'country' => function ($query) {
                    $query->select(['id', 'currency_code']);
                },
            ])
            ->whereHas('zones.shippingZoneCountry', function ($query) use ($countryId) {
                $query->where('country_id', $countryId);
            })
            ->find($storeId);

        if (! $store || ! $store->country) {
            return [];
        }

        $zoneRates = [];

        foreach ($store->zones as $zone) {
            foreach ($zone->shippingZoneRates as $rate) {
                $zoneRates[] = $rate;
            }
        }

        return $this->convertRatesForCountry($zoneRates, $store->country->currency_code, $countryId);
    }

    /**
     * Flat item-based rates attached to this product that ship to the country.
     */
    private function computeAndAggregateShippingDataForItemByCountry(int $itemId, int $countryId): array
    {
        $itemCondition = ShippingZoneRate::ZONE_CONDITION_TYPE[ShippingZoneRate::ZONE_CONDITION_ITEM];

        $item = Item::on('mysql_reader')
            ->select(['id', 'store_id'])
            ->with([
                'store' => function ($query) {
                    $query->select(['id', 'country_id'])
                        ->with([
                            'country' => function ($query) {
                                $query->select(['id', 'currency_code']);
                            },
                        ]);
                },
                'shippingZoneRates' => function ($query) use ($countryId, $itemCondition) {
                    $query->select([
                        'shipping_zone_rates.id',
                        'shipping_zone_rates.price',
                        'shipping_zone_rates.low_value',
                        'shipping_zone_rates.high_value',
                    ])
                        ->where('shipping_zone_rates.shipping_type', 1)
                        ->where('shipping_zone_rates.condition_type', $itemCondition)
                        ->whereHas('shippingZone.shippingZoneCountry', function ($q) use ($countryId) {
                            $q->where('country_id', $countryId);
                        });
                },
            ])
            ->find($itemId);

        if (! $item || ! $item->store || ! $item->store->country) {
            return [];
        }

        return $this->convertRatesForCountry(
            $item->shippingZoneRates,
            $item->store->country->currency_code,
            $countryId
        );
    }

    /**
     * @param  iterable<int, ShippingZoneRate>  $zoneRates
     */
    private function convertRatesForCountry(iterable $zoneRates, string $storeCurrency, int $countryId): array
    {
        $zoneCurrency = $this->countryService->getCurrencyCodeByCountryId($countryId);
        $rates = [];

        foreach ($zoneRates as $rate) {
            $convertedPrice = $this->currencyService->convert(
                $rate->price ?? 0,
                $storeCurrency,
                $zoneCurrency
            )['convertedPrice'] ?? null;

            if ($convertedPrice === null) {
                continue;
            }

            $convertedLow = $this->currencyService->convert(
                $rate->low_value ?? 0,
                $storeCurrency,
                $zoneCurrency
            )['convertedPrice'] ?? 0;

            $convertedHigh = $this->currencyService->convert(
                $rate->high_value ?? 0,
                $storeCurrency,
                $zoneCurrency
            )['convertedPrice'] ?? 0;

            $rates[] = [
                'rate' => $convertedPrice,
                'low' => $convertedLow,
                'high' => $convertedHigh,
                'type' => 'price',
            ];
        }

        return $rates;
    }

    /**
     * Cheapest rate, or the lowest threshold when every rate is conditional and none are free.
     */
    private function selectBestShippingRate(array $rates): ?array
    {
        $bestRateArray = collect($rates)->sortBy([
            ['rate', 'asc'],
            ['low', 'asc'],
        ])->values()->toArray();
        $bestRate = $bestRateArray[0] ?? null;
        $isNonFreeMultiple = count($bestRateArray) > 1 && ! array_filter($bestRateArray, function ($rate) {
            return $rate['rate'] === 0.0 || $rate['rate'] === 0;
        });

        if ($isNonFreeMultiple && count($bestRateArray) > 1) {
            $bestRate = collect($bestRateArray)->sortBy('low')->first();
        }
        if ($bestRate) {
            $bestRate['isNonFreeMultiple'] = $isNonFreeMultiple ? true : false;
        }

        return $bestRate;
    }

    private function getAllStoreIdsForFreeShipping(int $countryId): array
    {
        return Store::on('mysql_reader')
            ->where('status', 1)
            ->where('states', Store::STATES_ACTIVE)
            ->whereHas('zones', function ($query) use ($countryId) {
                $query->whereHas('shippingZoneCountry', function ($q) use ($countryId) {
                    $q->where('country_id', $countryId);
                })->whereHas('shippingZoneRates', function ($q) {
                    $q->where('shipping_type', 1)
                        ->whereNull('condition_type')
                        ->where('price', 0);
                });
            })
            ->pluck('id')
            ->toArray();
    }

    /**
     * @return int[]
     */
    private function getAllItemIdsForFreeShipping(int $countryId): array
    {
        return Item::on('mysql_reader')
            ->whereHas('shippingZoneRates', function ($q) use ($countryId) {
                $q->where('shipping_type', 1)
                    ->where('condition_type', ShippingZoneRate::ZONE_CONDITION_TYPE[ShippingZoneRate::ZONE_CONDITION_ITEM])
                    ->where('price', 0)
                    ->whereHas('shippingZone', function ($q) use ($countryId) {
                        $q->whereHas('shippingZoneCountry', fn ($q) => $q->where('country_id', $countryId))
                            ->whereHas('store', function ($q) {
                                $q->where('status', 1)
                                    ->where('states', Store::STATES_ACTIVE);
                            });
                    });
            })
            ->pluck('id')
            ->toArray();
    }

    public function getBestShippingRate(int $storeId, ?int $countryId): ?array
    {
        try {
            if (! $countryId) {
                return null;
            }

            $cacheKey = self::BEST_SHIPPING_RATE_CACHE_KEY_PREFIX.$storeId.'_'.$countryId;
            $bestRate = Cache::get($cacheKey);

            if (! $bestRate) {
                $rates = $this->computeAndAggregateShippingDataForStoreByCountry($storeId, $countryId);
                $bestRate = $this->selectBestShippingRate($rates);
                if ($bestRate) {
                    $bestRate['isProductRate'] = false;
                    Cache::put($cacheKey, $bestRate, self::CACHE_TTL);
                }
            }

            return $bestRate;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Best flat item rate for this product and country.
     * When the product has no item rate, the store rate is returned.
     */
    public function getBestProductShippingRate(int $itemId, int $storeId, ?int $countryId): ?array
    {
        try {
            if (! $countryId) {
                return null;
            }

            $cacheKey = self::BEST_PRODUCT_SHIPPING_RATE_CACHE_KEY_PREFIX.$storeId.'_'.$itemId.'_'.$countryId;
            $bestRate = Cache::get($cacheKey);

            if (! $bestRate) {
                $rates = $this->computeAndAggregateShippingDataForItemByCountry($itemId, $countryId);
                $bestRate = $this->selectBestShippingRate($rates);
                $bestStoreRate = $this->getBestShippingRate($storeId, $countryId);
                if (! empty($bestStoreRate) && (! $bestRate || $bestRate['rate'] > $bestStoreRate['rate'])) {
                    $bestRate = $bestStoreRate;
                } elseif ($bestRate) {
                    $bestRate['isProductRate'] = true;
                }

                if ($bestRate) {
                    Cache::put($cacheKey, $bestRate, self::CACHE_TTL);
                }
            }

            return $bestRate;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getFreeShippingStoreIds(?int $countryId): ?array
    {
        try {
            if (! $countryId) {
                return [];
            }
            $cacheKey = self::FREE_SHIPPING_CACHE_KEY_PREFIX.$countryId;
            $summary = Cache::get($cacheKey);
            if ($summary) {
                return $summary;
            }
            $storeIds = $this->getAllStoreIdsForFreeShipping($countryId);
            Cache::put($cacheKey, $storeIds, self::CACHE_TTL);

            return $storeIds;
        } catch (Exception $e) {
            return [];
        }
    }

    public function getFreeShippingItemIds(?int $countryId): ?array
    {
        try {
            if (! $countryId) {
                return [];
            }
            $cacheKey = self::FREE_SHIPPING_ITEM_CACHE_KEY_PREFIX.$countryId;
            $itemIds = Cache::get($cacheKey);
            if ($itemIds) {
                return $itemIds;
            }
            $itemIds = $this->getAllItemIdsForFreeShipping($countryId);
            Cache::put($cacheKey, $itemIds, self::CACHE_TTL);

            return $itemIds;
        } catch (Exception $e) {
            return [];
        }
    }
}
