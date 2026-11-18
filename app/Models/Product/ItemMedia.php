<?php

namespace App\Models\Product;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ItemMedia extends Model
{
    use ConvertTimeZone;

    protected $table = 'item_medias';

    protected $fillable = [
        'uuid',
        'item_id',
        'item_variant_id',
        'gallery_id',
        'order',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ItemMedia $media): void {
            $media->uuid ??= (string) Str::uuid();
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class, 'gallery_id');
    }
}
