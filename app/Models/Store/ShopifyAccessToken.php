<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

class ShopifyAccessToken extends Model
{
    protected $fillable = [
        'shop_domain_name',
        'store_id',
        'access_token',
    ];
}
