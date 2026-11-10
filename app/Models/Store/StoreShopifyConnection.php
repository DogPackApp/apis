<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreShopifyConnection extends Model
{
    protected $table = 'store_shopify_connections';

    protected $primaryKey = 'store_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'store_id',
    ];

    public $timestamps = false;

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
