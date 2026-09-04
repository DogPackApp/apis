<?php

namespace App\Services;

class StripeKeyService
{
    /**
     * Map country IDs to Stripe config keys. Default (US/CA) is the fallback
     * and doesn't need to be listed.
     *
     * @var array<int, string>
     */
    public const COUNTRY_CONFIG_MAP = [
        230 => 'marketplace_stripe_uk',
    ];

    public const DEFAULT_CONFIG = 'marketplace_stripe';

    /**
     * @return array<string, mixed>
     */
    public static function resolve(?int $countryId): array
    {
        return config('services.'.self::configKey($countryId));
    }

    public static function key(?int $countryId): string
    {
        return self::resolve($countryId)['key'];
    }

    public static function configKey(?int $countryId): string
    {
        return self::COUNTRY_CONFIG_MAP[$countryId] ?? self::DEFAULT_CONFIG;
    }
}
