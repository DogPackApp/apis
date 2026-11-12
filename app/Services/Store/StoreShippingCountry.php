<?php

namespace App\Services\Store;

use App\Models\Store\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreShippingCountry
{
    /**
     * Store ids that ship to the given countries and have finished onboarding.
     *
     * @param  array<int, int|string>  $countryIds
     * @return array<int, int>
     */
    public function getStoreIdsByCountry(array $countryIds): array
    {
        if ($countryIds === []) {
            return [];
        }

        $validCountryIds = array_filter($countryIds, function ($id) {
            return is_numeric($id) && $id > 0;
        });

        if ($validCountryIds === []) {
            return [];
        }

        sort($validCountryIds);

        try {
            return $this->fetchStoreIdsByCountryFromDatabase($validCountryIds);
        } catch (\Exception $e) {
            Log::error('Error fetching store IDs by country', [
                'country_ids' => $validCountryIds,
                'error' => $e->getMessage(),
            ]);

            return $this->fetchStoreIdsByCountryFromDatabase($validCountryIds);
        }
    }

    /**
     * @param  array<int, int|string>  $countryIds
     * @return array<int, int>
     */
    private function fetchStoreIdsByCountryFromDatabase(array $countryIds): array
    {
        try {
            $storeIds = DB::table('shipping_zones as sz')
                ->join('shipping_zone_country as szc', 'sz.id', '=', 'szc.shipping_zone_id')
                ->whereIn('szc.country_id', $countryIds)
                ->whereNull('sz.deleted_at')
                ->whereNull('szc.deleted_at')
                ->distinct()
                ->pluck('sz.store_id')
                ->toArray();

            if ($storeIds === []) {
                return [];
            }

            $storeIds = DB::table('stores')
                ->whereIn('id', $storeIds)
                ->where('states', Store::STATES_ACTIVE)
                ->pluck('id')
                ->toArray();

            return DB::table('onboarding_statuses')
                ->whereIn('store_id', $storeIds)
                ->where('is_product', 1)
                ->where('is_shipping', 1)
                ->where('is_store_setting', 1)
                ->where('is_finance', 1)
                ->where('is_subscribe', 1)
                ->pluck('store_id')
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Database error in fetchStoreIdsByCountryFromDatabase', [
                'country_ids' => $countryIds,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
