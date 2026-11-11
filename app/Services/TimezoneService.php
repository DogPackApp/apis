<?php

namespace App\Services;

use App\Models\Store\Store;
use Carbon\Carbon;

class TimezoneService
{
    private static $store;

    public static function convertToTimezone($datetime, $convertToTimezone = null)
    {
        if (! $datetime) {
            return null;
        }

        $utc = Carbon::parse($datetime)->utc();

        if ($convertToTimezone) {
            return $utc->setTimezone($convertToTimezone)->settings([
                'toJsonFormat' => 'Y-m-d\TH:i:sP',
            ]);
        }

        if (! self::$store) {
            if (app()->bound(Store::class)) {
                self::$store = resolve(Store::class);
            }
        }

        if (self::$store && self::$store->timezone) {
            return $utc->setTimezone(self::$store->timezone)->settings([
                'toJsonFormat' => 'Y-m-d\TH:i:sP',
            ]);
        }

        return $utc;
    }
}
