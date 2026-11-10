<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ItemCsv extends Model
{
    public $timestamps = false;

    protected $table = 'item_csvs';

    protected $fillable = [
        'product_id',
        'store_id',
        'item_id',
    ];

    public function item(): HasOne
    {
        return $this->hasOne(Item::class, 'id', 'item_id');
    }
}
