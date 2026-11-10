<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StoreSetting extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'store_id',
        'payout_days',
        'commission',
        'promo_contribution_percent',
        'subscribe_save_discount_percent',
        'dogpack_subscribe_save_first_order_percent',
        'subscribe_save_disabled',
    ];

    protected $casts = [
        'promo_contribution_percent' => 'decimal:2',
        'subscribe_save_discount_percent' => 'integer',
        'dogpack_subscribe_save_first_order_percent' => 'integer',
        'subscribe_save_disabled' => 'boolean',
    ];

    // Auto-generate UUID when creating a seller
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }
}
