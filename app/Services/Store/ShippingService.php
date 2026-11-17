<?php

namespace App\Services\Store;

use App\Models\Misc\Currency;
use App\Models\Misc\State;
use App\Models\Store\ShippingRate;
use App\Models\Store\ShippingStateExclusion;
use App\Models\Store\ShippingZone;
use App\Models\Store\ShippingZoneCountry;
use App\Models\Store\ShippingZoneRate;
use App\Models\Store\Store;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ShippingService
{
    public Store $store;

    public array $shipping;

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    public function deleteShipping(): ShippingService
    {
        $existingShippingZones = ShippingZone::query()
            ->where('store_id', $this->store->id)
            ->get()
            ->pluck('id')
            ->toArray();

        ShippingZoneRate::query()
            ->whereIn('shipping_zone_id', $existingShippingZones)
            ->delete();

        $shippingZoneCountriesIds = ShippingZoneCountry::query()
            ->whereIn('shipping_zone_id', $existingShippingZones)
            ->pluck('id')
            ->toArray();

        ShippingStateExclusion::query()
            ->whereIn('shipping_zone_country_id', $shippingZoneCountriesIds)
            ->delete();

        ShippingZoneCountry::query()
            ->whereIn('shipping_zone_id', $existingShippingZones)
            ->delete();

        ShippingZone::query()
            ->whereIn('id', $existingShippingZones)
            ->delete();

        return $this;
    }

    public function createShippingZones(array $shippings): void
    {
        foreach ($shippings as $shipping) {
            $shippingZone = $this->createShippingZone([
                'name' => $shipping['name'],
                'uuid' => $shipping['uuid'] ?? Str::uuid(),
                'store_id' => $this->store->id,
            ]);

            $this->createShippingZoneCountries($shippingZone->id, $shipping['country']);

            if (! empty($shipping['excluded_states'] ?? [])) {
                $this->createShippingStateExclusion($shippingZone->id, $shipping['excluded_states']);
            }

            foreach ($shipping['shipping_zone_rates'] as $zoneRate) {
                if ((int) $zoneRate['shipping_type'] == 1) {
                    $shippingRate = $this->getOrCreateShippingRate($zoneRate);
                    $currency = null;
                }

                $this->createShippingZoneRate($shippingZone->id, $shippingRate->id ?? null, $currency->id ?? null, $zoneRate);
            }
        }
    }

    private function createShippingZone(array $shippingZone): ShippingZone
    {
        return ShippingZone::updateOrCreate(
            ['uuid' => $shippingZone['uuid']],
            $shippingZone,
        );
    }

    private function createShippingZoneCountries(int $shippingZoneId, array $countryIds): void
    {
        $zoneCountries = array_map(fn ($countryId) => [
            'uuid' => Str::uuid(),
            'shipping_zone_id' => $shippingZoneId,
            'country_id' => $countryId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $countryIds);

        ShippingZoneCountry::insert($zoneCountries);
    }

    public function createCustomShippingMethod($name, $description)
    {
        return ShippingRate::updateOrCreate(
            [
                'store_id' => resolve(Store::class)->id,
                'name' => $name,
                'description' => $description,
            ]
        );
    }

    public function updateCustomShippingMethod(string $shippingRateUuid, string $name, string $description): ShippingRate
    {
        $shippingRate = ShippingRate::where('uuid', $shippingRateUuid)->firstOrFail();

        $shippingRate->name = $name;
        $shippingRate->description = $description;
        $shippingRate->save();

        return $shippingRate;
    }

    private function createShippingStateExclusion(int $shippingZoneId, array $countryStateIds): void
    {
        $shippingZoneCountryIds = ShippingZoneCountry::query()
            ->select('id', 'country_id')
            ->where('shipping_zone_id', $shippingZoneId)
            ->get()
            ->keyBy('country_id')
            ->toArray();

        $stateExclusions = [];
        foreach ($countryStateIds as $countryId => $stateIds) {
            if (! isset($shippingZoneCountryIds[$countryId])) {
                throw new InvalidArgumentException(
                    "Country {$countryId} does not belong to shipping zone {$shippingZoneId}"
                );
            }

            $stateIds = array_unique($stateIds);

            $validStateCount = State::query()
                ->where('country_id', $countryId)
                ->whereIn('id', $stateIds)
                ->count();

            if ($validStateCount !== count($stateIds)) {
                throw new InvalidArgumentException(
                    "One or more excluded states do not belong to country {$countryId}"
                );
            }

            foreach ($stateIds as $stateId) {
                $stateExclusions[] = [
                    'shipping_zone_country_id' => $shippingZoneCountryIds[$countryId]['id'],
                    'state_id' => $stateId,
                ];
            }
        }

        if ($stateExclusions !== []) {
            ShippingStateExclusion::insert($stateExclusions);
        }
    }

    private function getOrCreateShippingRate(array $shippingRates): ShippingRate
    {
        // previously, it was directly: $shippingRates['shipping_rates']
        // now, due to some FE issues, Utsav is sending me as an object
        $uuid = $shippingRates['shipping_rates']['value'] ?? 'custom';

        if ($uuid === 'custom') {
            return ShippingRate::updateOrCreate(
                ['uuid' => $shippingRates['shipping_rates']],
                [
                    'store_id' => $this->store->id,
                    'name' => $shippingRates['shipping_rates_custom']['name'],
                    'description' => $shippingRates['shipping_rates_custom']['description'],
                ]
            );
        }

        return ShippingRate::where('uuid', $uuid)
            ->firstOrFail();
    }

    private function getCurrency(int $currencyId): Currency
    {
        return Currency::findOrFail($currencyId);
    }

    private function createShippingZoneRate(int $shippingZoneId, ?int $shippingRateId, ?int $currencyId, array $shippingRates)
    {
        $isCarrierRate = (int) $shippingRates['shipping_type'] == 2;

        if ($isCarrierRate) {
            return ShippingZoneRate::updateOrCreate(
                [
                    'uuid' => $shippingRates['uuid'] ?? Str::uuid(),
                ],
                [
                    'shipping_zone_id' => $shippingZoneId,
                    'shipping_type' => $shippingRates['shipping_type'],
                ]
            );
        }

        if ($shippingRates['condition_type'] === null) {
            $shippingRates['low_value'] = null;
            $shippingRates['high_value'] = null;
        }

        $weight = 'LB';

        if ($shippingRates['condition_type'] === 'WEIGHT') {
            if ($shippingRates['weight'] != null) {
                $weight = $shippingRates['weight'];
            }
        } else {
            $weight = null;
        }

        return ShippingZoneRate::updateOrCreate(
            [
                'uuid' => $shippingRates['uuid'] ?? Str::uuid(),
            ],
            [
                'shipping_zone_id' => $shippingZoneId,
                'shipping_rate_id' => $shippingRateId,
                'shipping_type' => $shippingRates['shipping_type'],
                'price' => $shippingRates['price'],
                'currency_id' => null,
                'condition_type' => isset($shippingRates['condition_type']) ? ShippingZoneRate::ZONE_CONDITION_TYPE[$shippingRates['condition_type']] : null,
                'low_value' => $shippingRates['low_value'] ?? null,
                'high_value' => $shippingRates['high_value'] ?? null,
                'weight' => $weight,
            ]
        );
    }
}
