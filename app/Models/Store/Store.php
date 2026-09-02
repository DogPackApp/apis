<?php

namespace App\Models\Store;

use App\Models\Seller\Seller;
use App\Support\Media;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    public const STATES_PENDING = 'PENDING';

    public const STATES_ACTIVE = 'ACTIVE';

    public const STATES_INACTIVE = 'INACTIVE';

    protected $fillable = [
        'uuid',
        'seller_id',
        'name',
        'slug',
        'description',
        'image',
        'cover_image',
        'social_links',
        'status',
        'states',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'social_links' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Store $store): void {
            $store->uuid ??= (string) Str::uuid();
            $store->slug ??= static::uniqueSlug($store->name);
        });
    }

    protected static function newFactory(): StoreFactory
    {
        return StoreFactory::new();
    }

    protected static function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $suffix = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$original}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => Media::url($value),
        );
    }

    protected function coverImage(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => Media::url($value),
        );
    }
}
