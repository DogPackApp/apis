<?php

namespace App\Models\Subscribe;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplacePlanPrice extends Model
{
    protected $table = 'marketplace_plan_prices';

    protected $fillable = [
        'plan_id',
        'country_code',
        'uuid',
        'country',
        'currency',
        'currency_symbol',
        'amount',
        'price_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MarketplacePlan::class, 'plan_id', 'id');
    }
}
