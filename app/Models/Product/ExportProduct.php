<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportProduct extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'product_uuid',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'product_uuid', 'uuid');
    }
}
