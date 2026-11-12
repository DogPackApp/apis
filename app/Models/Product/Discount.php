<?php

namespace App\Models\Product;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Discount extends Model
{
    use ConvertTimeZone;

    protected $fillable = [
        'item_id',
        'value',
        'is_limited',
        'start_date',
        'end_date',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Discount $discount): void {
            $discount->uuid ??= (string) Str::uuid();
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
