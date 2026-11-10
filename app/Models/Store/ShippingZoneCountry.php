<?php

namespace App\Models\Store;

use App\Models\Misc\Country;
use App\Models\Misc\State;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ShippingZoneCountry extends Model
{
    use SoftDeletes;

    protected $table = 'shipping_zone_country';

    protected $fillable = [
        'shipping_zone_id',
        'store_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($country): void {
            $country->uuid = Str::uuid();
        });
    }

    public function country(): HasOne
    {
        return $this->hasOne(Country::class, 'id', 'country_id');
    }

    public function excludedStates(): BelongsToMany
    {
        return $this->belongsToMany(
            State::class,
            'shipping_state_exclusions',
            'shipping_zone_country_id',
            'state_id'
        );
    }

    public function excludedStateIds(): HasMany
    {
        return $this->hasMany(ShippingStateExclusion::class);
    }
}
