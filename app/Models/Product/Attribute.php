<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Attribute extends Model
{
    protected $fillable = [
        'uuid',
        'item_id',
        'name',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Attribute $attribute): void {
            $attribute->uuid ??= (string) Str::uuid();
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function attributeOptions(): HasMany
    {
        $options = $this->hasMany(AttributeOption::class);

        if (DB::getDriverName() === 'mysql') {
            return $options->orderByRaw('CAST(attribute_value AS UNSIGNED), attribute_value');
        }

        return $options->orderBy('attribute_value');
    }
}
