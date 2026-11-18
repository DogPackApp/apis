<?php

namespace App\Models\Store;

use App\Models\Misc\Currency;
use App\Models\Product\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ShippingZoneRate extends Model
{
    public const ZONE_CONDITION_WEIGHT = 'WEIGHT';

    public const ZONE_CONDITION_PRICE = 'PRICE';

    public const ZONE_CONDITION_ITEM = 'ITEM';

    public const ZONE_CONDITION_TYPE = [
        self::ZONE_CONDITION_WEIGHT => 1,
        self::ZONE_CONDITION_PRICE => 2,
        self::ZONE_CONDITION_ITEM => 3,
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

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function itemIds(): HasMany
    {
        return $this->hasMany(ShippingZoneRateItem::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'shipping_zone_rate_items', 'shipping_zone_rate_id', 'item_id');
    }
}
