<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportOrders extends Model
{
    protected $primaryKey = 'batch_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'store_id',
        'filters',
        'created_at',
        'finished_at',
        'errors',
    ];

    protected $casts = [
        'filters' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
