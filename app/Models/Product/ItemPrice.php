<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ItemPrice extends Model
{
    protected $fillable = [
        'uuid',
        'item_id',
        'sku',
        'gtin',
        'domestic_price',
        'global_price',
        'is_global_pricing',
        'stock_quantity',
        'weight',
        'unit',
        'allow_overselling',
    ];

    protected function casts(): array
    {
        return [
            'domestic_price' => 'integer',
            'global_price' => 'integer',
            'is_global_pricing' => 'boolean',
            'stock_quantity' => 'integer',
            'weight' => 'integer',
            'unit' => 'integer',
            'allow_overselling' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ItemPrice $price): void {
            $price->uuid ??= (string) Str::uuid();
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
