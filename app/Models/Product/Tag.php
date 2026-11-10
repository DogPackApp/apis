<?php

namespace App\Models\Product;

use App\Models\Seller\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'seller_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($tag): void {
            $tag->uuid = Str::uuid();
        });
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_tags', 'tags_id', 'item_id');
    }
}
