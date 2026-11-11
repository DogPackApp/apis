<?php

namespace App\Services\Order;

use App\Models\Misc\Country;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CountryService
{
    protected string $defaultCurrencyCode = 'CAD';

    const DEFAULT_COUNTRY_ID = 38;

    const CACHE_TTL = 86400; // 24 hours

    // Country Codes
    const COUNTRY_CODES = [
        'UK' => 'GB',
        'US' => 'US',
        'CA' => 'CA',
        'AU' => 'AU',
    ];

    /**
     * Get all countries from database with optional language translation
     *
     * @param  string  $language  Language code (e.g., 'en', 'fr', 'es')
     * @return Collection
     */
    public function getAllCountries(string $language = 'en')
    {
        $cacheKey = 'website:marketplace:countries:marketplace_all_countries.'.$language;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($language) {
            if ($language === 'en') {
                return Country::orderBy('order', 'asc')->orderBy('name', 'asc')->get();
            }

            return Country::leftJoin('countries_translation', function ($join) use ($language) {
                $join->on('marketplace_countries.id', '=', 'countries_translation.country_id')
                    ->where('countries_translation.lang_type', '=', $language);
            })
                ->selectRaw('
                    marketplace_countries.*,
                    COALESCE(countries_translation.name, marketplace_countries.name) as name
                ')
                ->orderBy('order', 'asc')->orderBy('name', 'asc')
                ->get();
        });
    }

    /**
     * Get countries for front/mobile display with language support
     */
    public function getCountriesForDisplay(bool $isMobile = false, string $language = 'en'): array
    {
        $cacheKey = 'marketplace_countries_display_'.($isMobile ? 'mobile' : 'front').'.'.$language;

        // return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($isMobile, $language) {
        $allCountries = $this->getAllCountries($language);

        $field = $isMobile ? 'is_display_mobile' : 'is_display_front';

        return $allCountries->where($field, 1)->map(function ($country) {
            return [
                'id' => $country->id,
                'name' => $country->name,
                'short_name' => $country->short_name,
                'currency_code' => $country->currency_code,
                'currency_name' => $country->currency_name,
                'currency_symbol' => $country->currency_symbol,
                'src' => $this->flagURL($country->short_name),
            ];
        })->values()->all();
        // });
    }

    /**
     * Get country by ID with language support
     */
    public function getCountryById(int $id, string $language = 'en'): ?array
    {
        $country = $this->getAllCountries($language)->firstWhere('id', $id);

        if ($country) {
            return [
                'id' => $country->id,
                'name' => $country->name,
                'short_name' => $country->short_name,
                'currency_code' => $country->currency_code,
                'currency_name' => $country->currency_name,
                'currency_symbol' => $country->currency_symbol,
                'src' => $this->flagURL($country->short_name),
            ];
        }

        return null;
    }

    /**
     * Get country by short code with language support
     */
    public function getByShortCodeWithLanguage(string $code, string $language = 'en'): ?array
    {
        $country = $this->getAllCountries($language)->firstWhere('short_name', $code);

        return $country ? $country->toArray() : null;
    }

    /**
     * Get currency code by country ID
     */
    public function getCurrencyCodeByCountryId(?int $countryId): string
    {
        if ($countryId === null) {
            return $this->defaultCurrencyCode;
        }

        $country = $this->getAllCountries('en')->firstWhere('id', $countryId);

        return $country->currency_code ?? $this->defaultCurrencyCode;
    }

    /**
     * Get country by currency code
     */
    public function getByCurrencyCode(string $code, string $language = 'en'): ?array
    {
        $country = $this->getAllCountries($language)->firstWhere('currency_code', $code);

        return $country ? $country->toArray() : null;
    }

    /**
     * Clear all country caches
     */
    public function clearCache(): void
    {
        // Clear all possible language variants
        $languages = ['en', 'fr', 'es', 'de', 'it', 'pt', 'nl', 'pl', 'ru', 'ja', 'zh', 'ar', 'hi']; // Add more as needed

        foreach ($languages as $lang) {
            Cache::forget('marketplace_all_countries.'.$lang);
            Cache::forget('marketplace_countries_display_front.'.$lang);
            Cache::forget('marketplace_countries_display_mobile.'.$lang);
        }
    }

    /**
     * Get flag image URL
     */
    private function flagURL(string $shortName): string
    {
        $assetsUrl = (string) config('services.website_assets_url');
        $parsed = parse_url($assetsUrl);
        $cdnBase = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? 'cdn-web-asset-1.dogpackapp.com');

        return $cdnBase.'/countryflags/'.strtolower($shortName).'.webp';
    }

    /**
     * Check if a given country code matches a target country code.
     */
    public function matchCountryCode(?string $countryCode, string $targetCode): bool
    {
        return strtoupper($countryCode ?? '') === strtoupper($targetCode);
    }

    // ========================================
    // Backward Compatibility Methods
    // These wrapper methods maintain the old function signatures
    // ========================================

    /**
     * Get country by short code (backward compatible)
     *
     * @param  string  $code
     * @return array|null
     */
    public function getByShortCode($code)
    {
        return $this->getByShortCodeWithLanguage($code, 'en');
    }

    /**
     * Get all countries as array (backward compatible)
     */
    public function all(): array
    {
        return $this->getAllCountries('en')->toArray();
    }

    /**
     * Get countries list for display front (backward compatible)
     */
    public function getCountriesListDisplayFront(bool $isMobile = false, string $language = 'en'): array
    {
        return $this->getCountriesForDisplay($isMobile, $language);
    }

    /**
     * Get country with all details by ID (backward compatible)
     */
    public function getCountryWithAllDetails(int $id): ?array
    {
        return $this->getCountryById($id, 'en');
    }

    /**
     * Get country display front by ID (backward compatible)
     */
    public function getCountryDisplayFrontById(int $id): ?array
    {
        $country = $this->getCountryById($id, 'en');

        // Check if country has is_display_front flag
        $fullCountry = $this->getAllCountries('en')->firstWhere('id', $id);
        if ($fullCountry && $fullCountry->is_display_front == 1) {
            return $country;
        }

        return null;
    }
}
