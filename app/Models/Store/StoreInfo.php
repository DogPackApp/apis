<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreInfo extends Model
{
    protected $table = 'stores_info';

    protected $fillable = [
        'store_id',
        'vat_number',
    ];

    protected function casts(): array
    {
        return [
            'store_id' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
