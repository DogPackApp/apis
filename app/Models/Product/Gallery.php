<?php

namespace App\Models\Product;

use App\Models\Store\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Gallery extends Model
{
    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    protected $fillable = [
        'uuid',
        'store_id',
        'url',
        'type',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Gallery $gallery): void {
            $gallery->uuid ??= (string) Str::uuid();
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
