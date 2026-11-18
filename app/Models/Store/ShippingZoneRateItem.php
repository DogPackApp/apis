<?php

namespace App\Models\Store;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;

class ShippingZoneRateItem extends Model
{
    use ConvertTimeZone;

    const UPDATED_AT = null;

    public $incrementing = false;

    protected $fillable = [
        'shipping_zone_rate_id',
        'item_id',
    ];
}
