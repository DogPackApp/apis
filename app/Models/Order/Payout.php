<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Payout extends Model
{
    const STATUS = [
        Payout::UPCOMING,
        Payout::DEPOSITED,
        'all', // for filter
    ];

    const UPCOMING = 'upcoming';

    const DEPOSITED = 'deposited';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }

    public function details(): HasMany
    {
        return $this->hasMany(PayoutDetail::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(StoreOrder::class, PayoutDetail::class, 'payout_id', 'id', 'id', 'store_order_id');
    }
}
