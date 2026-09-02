<?php

namespace App\Support;

use Carbon\Carbon;

class Timezone
{
    public static function convert(?Carbon $value, ?string $timezone): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return $value->clone()->setTimezone($timezone ?: 'UTC');
    }
}
