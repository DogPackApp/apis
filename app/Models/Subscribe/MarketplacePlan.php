<?php

namespace App\Models\Subscribe;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplacePlan extends Model
{
    protected $table = 'marketplace_plans';

    protected $fillable = [
        'plan_key',
        'plan_id',
    ];

    public function prices(): HasMany
    {
        return $this->hasMany(MarketplacePlanPrice::class, 'plan_id', 'id');
    }
}
