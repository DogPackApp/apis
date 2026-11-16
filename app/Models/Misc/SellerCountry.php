<?php

namespace App\Models\Misc;

use Illuminate\Database\Eloquent\Model;

class SellerCountry extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'seller_countries';

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'short_name',
        'name',
        'currency_code',
        'currency_name',
        'currency_symbol',
    ];
}
