<?php

namespace App\Models\Stripe;

use App\Models\Store\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreSubscription extends Model
{
    protected $table = 'store_subscriptions';

    protected $guarded = [];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
