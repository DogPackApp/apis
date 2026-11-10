<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ShippingZone extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'store_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($zone): void {
            $zone->uuid = Str::uuid();
        });
    }

    public function shippingZoneCountry(): HasMany
    {
        return $this->hasMany(ShippingZoneCountry::class);
    }

    public function shippingZoneRates(): HasMany
    {
        return $this->hasMany(ShippingZoneRate::class);
    }
}
