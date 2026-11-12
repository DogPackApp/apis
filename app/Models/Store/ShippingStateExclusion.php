<?php

namespace App\Models\Store;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;

class ShippingStateExclusion extends Model
{
    use ConvertTimeZone;

    public $incrementing = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'shipping_zone_country_id',
        'state_id',
    ];
}
