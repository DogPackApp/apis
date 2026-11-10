<?php

namespace App\Models\Product;

use App\Models\Store\Policy;
use App\Models\Store\Section;
use App\Models\Store\Store;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_DIGITAL = 'DIGITAL';

    public const TYPE_PHYSICAL = 'PHYSICAL';

    public const TYPES = [
        self::TYPE_DIGITAL => 1,
        self::TYPE_PHYSICAL => 2,
    ];

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_ARCHIVED = 'ARCHIVED';

    public const STATUS_DELETED = 'DELETED';

    public const STATUS = [
        self::STATUS_ACTIVE => 1,
        self::STATUS_DRAFT => 2,
        self::STATUS_ARCHIVED => 3,
        self::STATUS_DELETED => 4,
    ];

    public const WEIGHT_UNIT_G = 'G';

    public const WEIGHT_UNIT_KG = 'KG';

    public const WEIGHT_UNIT_MG = 'MG';

    public const WEIGHT_UNIT_OZ = 'OZ';

    public const WEIGHT_UNIT_LB = 'LB';

    public const ITEM_WEIGHT = [
        self::WEIGHT_UNIT_G => 1,
        self::WEIGHT_UNIT_KG => 2,
        self::WEIGHT_UNIT_MG => 3,
        self::WEIGHT_UNIT_OZ => 4,
        self::WEIGHT_UNIT_LB => 5,
    ];

    protected $fillable = [
        'uuid',
        'store_id',
        'category_id',
        'policy_id',
        'type',
        'title',
        'slug',
        'description',
        'status',
        'origin',
        'source_id',
        'media_url',
        'manually_deactivated',
    ];

    protected function casts(): array
    {
        return [
            'type' => 'integer',
            'status' => 'integer',
            'manually_deactivated' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Item $item): void {
            $item->uuid ??= (string) Str::uuid();
        });
    }

    protected static function newFactory(): ItemFactory
    {
        return ItemFactory::new();
    }

    protected static function booted(): void
    {
        static::addGlobalScope('excludeDeletedItems', function (Builder $builder): void {
            $builder->where('items.status', '!=', self::STATUS[self::STATUS_DELETED]);
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class);
    }

    public function price(): HasOne
    {
        return $this->hasOne(ItemPrice::class);
    }

    public function medias(): BelongsToMany
    {
        return $this->belongsToMany(Gallery::class, 'item_medias', 'item_id', 'gallery_id')
            ->whereNull('item_medias.item_variant_id')
            ->withPivot('order')
            ->orderBy('item_medias.order', 'asc');
    }

    public function media(): HasOneThrough
    {
        return $this->hasOneThrough(Gallery::class, ItemMedia::class, 'item_id', 'id', 'id', 'gallery_id')
            ->where('order', 0);
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'item_tags', 'item_id', 'tags_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ItemVariant::class);
    }

    public function itemVariants(): HasMany
    {
        return $this->hasMany(ItemVariant::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(Attribute::class);
    }

    public function varyBy(): HasOne
    {
        return $this->hasOne(VaryBy::class);
    }

    public function discount(): HasOne
    {
        return $this->hasOne(Discount::class);
    }

    public function customization(): HasOne
    {
        return $this->hasOne(Customization::class);
    }

    public function unverified(): HasOne
    {
        return $this->hasOne(ItemUnverified::class);
    }

    public function activeFacets(): BelongsToMany
    {
        return $this->belongsToMany(FacetValue::class, 'item_facet_mappings', 'item_id', 'facet_value_id')
            ->where('facet_values.is_active', 1);
    }

    public function isShopifyProduct(): bool
    {
        return $this->source_id !== null;
    }

    public function isActive(): bool
    {
        return $this->deleted_at === null
            && (int) $this->status === self::STATUS[self::STATUS_ACTIVE]
            && (bool) $this->manually_deactivated !== true;
    }

    public function stock(): string
    {
        $quantity = $this->price?->stock_quantity ?? 0;

        return "{$quantity} in Stock";
    }
}
