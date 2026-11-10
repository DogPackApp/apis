<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VariantCombination extends Model
{
    protected $fillable = [
        'uuid',
        'item_variant_id',
        'attribute_option_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (VariantCombination $combination): void {
            $combination->uuid ??= (string) Str::uuid();
        });
    }

    public function itemVariant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class);
    }

    public function attributeOption(): BelongsTo
    {
        return $this->belongsTo(AttributeOption::class);
    }
}
