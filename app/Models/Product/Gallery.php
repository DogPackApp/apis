<?php

namespace App\Models\Product;

use App\Models\Concerns\ConvertTimeZone;
use App\Models\Store\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Gallery extends Model
{
    use ConvertTimeZone;

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

    public function getUrlAttribute(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $base = config('services.marketplace_media');

        if ($base === null || $base === '') {
            return $value;
        }

        return $base.$value;
    }
}
