<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Customization extends Model
{
    protected $fillable = [
        'item_id',
        'description',
        'description_enabled',
        'limit',
        'attach_file',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Customization $customization): void {
            $customization->uuid ??= (string) Str::uuid();
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
