<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class AttributeOption extends Model
{
    protected $fillable = [
        'uuid',
        'attribute_id',
        'attribute_value',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AttributeOption $option): void {
            $option->uuid ??= (string) Str::uuid();
        });
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function itemVariants(): BelongsToMany
    {
        return $this->belongsToMany(
            ItemVariant::class,
            'variant_combinations',
            'attribute_option_id',
            'item_variant_id',
        );
    }
}
