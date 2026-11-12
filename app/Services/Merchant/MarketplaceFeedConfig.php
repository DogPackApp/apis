<?php

namespace App\Services\Merchant;

/**
 * Constants shared by every marketplace feed (Google products, Awin, Google promotions)
 * and the commands/jobs that run them. Anything specific to one feed (file paths,
 * headers, category maps) stays in that feed's own class.
 */
final class MarketplaceFeedConfig
{
    public const GOOGLE_NS = 'http://base.google.com/ns/1.0';

    public const SITE_URL = 'https://www.dogpackapp.com';

    public const BATCH_SIZE = 1000;

    public const COUNTRY_CANADA = 38;

    public const COUNTRY_USA = 231;

    public const COUNTRY_UK = 230;

    /** Country code => country id. Drives the --country option and the queued jobs. */
    public const COUNTRY_IDS = [
        'CA' => self::COUNTRY_CANADA,
        'US' => self::COUNTRY_USA,
        'GB' => self::COUNTRY_UK,
    ];

    /** Country id => country code. */
    public const COUNTRY_CODES = [
        self::COUNTRY_CANADA => 'CA',
        self::COUNTRY_USA => 'US',
        self::COUNTRY_UK => 'GB',
    ];

    /** Country id => feed currency. */
    public const CURRENCIES = [
        self::COUNTRY_CANADA => 'CAD',
        self::COUNTRY_USA => 'USD',
        self::COUNTRY_UK => 'GBP',
    ];

    /** Country code => storefront URL prefix. */
    public const LOCALE_PREFIXES = [
        'CA' => '/ca',
        'GB' => '/gb',
        'US' => '',
    ];

    /** Used when a country id is not in the maps above, as the product feed always has. */
    public const DEFAULT_COUNTRY_CODE = 'US';

    public const DEFAULT_CURRENCY = 'CAD';
}
