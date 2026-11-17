<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ItemUnverified extends Model
{
    protected $table = 'item_unverified';

    protected $fillable = [
        'item_id',
        'store_id',
    ];
}
