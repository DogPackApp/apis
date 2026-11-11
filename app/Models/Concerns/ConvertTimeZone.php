<?php

namespace App\Models\Concerns;

use App\Services\TimezoneService;

trait ConvertTimeZone
{
    public function getCreatedAtAttribute($value)
    {
        return TimezoneService::convertToTimezone($value);
    }

    public function getUpdatedAtAttribute($value)
    {
        return TimezoneService::convertToTimezone($value);
    }

    public function getNextPaymentAttemptAttribute($value)
    {
        return TimezoneService::convertToTimezone($value);
    }

    public function getCurrentPeriodStartAttribute($value)
    {
        return TimezoneService::convertToTimezone($value);
    }

    public function getCurrentPeriodEndAttribute($value)
    {
        return TimezoneService::convertToTimezone($value);
    }
}
