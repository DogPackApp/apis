<?php

namespace App\Models\Store;

use App\Models\Product\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Section extends Model
{
    protected $fillable = [
        'store_id',
        'name',
        'slug',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($section): void {
            $section->uuid = Str::uuid();
        });
    }

    protected static function booted(): void
    {
        static::updated(fn (Section $section) => Cache::forget("store_sections_{$section->store_id}"));
        static::deleted(fn (Section $section) => Cache::forget("store_sections_{$section->store_id}"));
        static::created(fn (Section $section) => Cache::forget("store_sections_{$section->store_id}"));
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_section')
            ->withPivot('order')
            ->withTimestamps()
            ->orderByPivot('order', 'asc')
            ->orderByPivot('updated_at', 'desc');
    }
}
