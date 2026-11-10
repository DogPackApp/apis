<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ShippingRate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'store_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($rate): void {
            $rate->uuid = Str::uuid();
        });
    }
}
