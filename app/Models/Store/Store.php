<?php

namespace App\Models\Store;

use App\Models\Misc\Currency;
use App\Models\Misc\Language;
use App\Models\Misc\SellerCountry;
use App\Models\Seller\Seller;
use App\Models\Stripe\StorePayment;
use App\Models\Stripe\StoreSubscription;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    public const STATES_PENDING = 'PENDING';

    public const STATES_ACTIVE = 'ACTIVE';

    public const STATES_INACTIVE = 'INACTIVE';

    public const STRIPE_STATUS_PENDING = 'pending';

    public const STRIPE_STATUS_VERIFIED = 'verified';

    public const STRIPE_STATUS_MORE_DETAILS = 'more_details';

    public const STRIPE_STATUS_RESTRICTED = 'restricted';

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
        'country_id',
        'currency_id',
        'language_id',
        'is_verified',
        'stripe_account_id',
        'stripe_status',
        'stripe_customer_id',
        'pm_type',
        'pm_last_four',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'social_links' => 'array',
            'country_id' => 'integer',
            'currency_id' => 'integer',
            'language_id' => 'integer',
            'is_verified' => 'boolean',
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

    public function settings(): HasOne
    {
        return $this->hasOne(StoreSetting::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(SellerCountry::class, 'country_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'language_id');
    }

    public function storeInfo(): HasOne
    {
        return $this->hasOne(StoreInfo::class, 'store_id');
    }

    public function shopifyConnectedShop(): HasOne
    {
        return $this->hasOne(ShopifyAccessToken::class, 'store_id');
    }

    public function getIsShopifyConnectedAttribute(): bool
    {
        if ($this->shopifyConnectedShop()->exists()) {
            return true;
        }

        return StoreShopifyConnection::query()
            ->where('store_id', $this->id)
            ->exists();
    }

    public static function _storeExists(): bool
    {
        return static::query()
            ->where('seller_id', auth()->user()->id)
            ->exists();
    }

    public function address(): HasOne
    {
        return $this->hasOne(ShippingAddress::class, 'store_id');
    }

    public function storesubscription(): HasOne
    {
        return $this->hasOne(StoreSubscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StorePayment::class);
    }

    public static function _defaultStoreSection(): ?Model
    {
        return Section::query()
            ->where('store_id', resolve(self::class)->id)
            ->where('slug', 'all')
            ->first();
    }

    protected function image(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if (empty($value)) {
                    return $value;
                }

                if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                    return $value;
                }

                return config('services.marketplace_media').$value;
            },
        );
    }

    protected function coverImage(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if (empty($value)) {
                    return $value;
                }

                if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                    return $value;
                }

                return config('services.marketplace_media').$value;
            },
        );
    }
}
