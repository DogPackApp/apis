<?php

namespace App\Models\Product;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VaryBy extends Model
{
    use ConvertTimeZone;

    protected $table = 'vary_by';

    protected $fillable = [
        'uuid',
        'item_id',
        'sku',
        'quantity',
        'price',
        'weight',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (VaryBy $varyBy): void {
            $varyBy->uuid ??= (string) Str::uuid();
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
