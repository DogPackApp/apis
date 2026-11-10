<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class ItemVariant extends Model
{
    protected $fillable = [
        'uuid',
        'item_id',
        'sku',
        'gtin',
        'domestic_price',
        'global_price',
        'stock_quantity',
        'weight',
        'is_visible',
        'allow_overselling',
        'order',
        'source_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ItemVariant $variant): void {
            $variant->uuid ??= (string) Str::uuid();
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function attributeOptions(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeOption::class,
            'variant_combinations',
            'item_variant_id',
            'attribute_option_id',
        );
    }
}
