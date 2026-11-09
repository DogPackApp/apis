<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

class ShippingStateExclusion extends Model
{
    public $incrementing = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'shipping_zone_country_id',
        'state_id',
    ];
}
