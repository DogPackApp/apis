<?php

namespace App\Models\Store;

use App\Models\Misc\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ShippingZoneRate extends Model
{
    public const ZONE_CONDITION_WEIGHT = 'WEIGHT';

    public const ZONE_CONDITION_PRICE = 'PRICE';

    public const ZONE_CONDITION_TYPE = [
        self::ZONE_CONDITION_WEIGHT => 1,
        self::ZONE_CONDITION_PRICE => 2,
    ];

    use SoftDeletes;

    protected $fillable = [
        'name',
        'shipping_zone_id',
        'shipping_rate_id',
        'shipping_type',
        'price',
        'currency_id',
        'condition_type',
        'low_value',
        'high_value',
        'weight',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($rate): void {
            $rate->uuid = Str::uuid();
        });
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function shippingRate(): BelongsTo
    {
        return $this->belongsTo(ShippingRate::class);
    }
}
