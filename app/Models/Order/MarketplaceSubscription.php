<?php

namespace App\Models\Order;

use App\Models\Product\Item;
use App\Models\Product\ItemVariant;
use App\Models\Store\Store;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MarketplaceSubscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED_PAYMENT = 'paused_payment';

    /** Three renewals in a row found no shipping rate for the product on its own. */
    public const STATUS_PAUSED_SHIPPING = 'paused_shipping';

    public const STATUS_CANCELLED = 'cancelled';

    public const OPEN_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_PAUSED_PAYMENT,
        self::STATUS_PAUSED_SHIPPING,
    ];

    public const PAUSED_STATUSES = [
        self::STATUS_PAUSED_PAYMENT,
        self::STATUS_PAUSED_SHIPPING,
    ];

    protected $table = 'marketplace_subscriptions';

    protected $fillable = [
        'uuid',
        'user_id',
        'email',
        'store_id',
        'item_id',
        'item_variant_id',
        'quantity',
        'customisation_text',
        'customisation_media',
        'frequency_days',
        'status',
        'origin_order_at',
        'next_order_at',
        'shipping_address',
        'frozen_shipping_amount',
        'frozen_shipping_method',
        'country_id',
        'currency',
        'stripe_customer_id',
        'stripe_payment_method_id',
        'locked_seller_discount_percent',
        'dogpack_first_order_percent_applied',
        'auto_apply_rewards',
        'last_paid_item_price',
        'price_rise_notified_unit_price',
        'price_rise_notified_at',
        'payment_attempt_count',
        'paused_at',
        'cancelled_at',
        'oos_catchup_pending',
        'last_paid_store_order_id',
        'manage_token_hash',
    ];

    /** Seconds a renewal or a customer change may hold a subscription. */
    public const LOCK_SECONDS = 120;

    protected function casts(): array
    {
        return [
            'origin_order_at' => 'datetime',
            'next_order_at' => 'datetime',
            'shipping_address' => 'array',
            'customisation_media' => 'array',
            'frozen_shipping_amount' => 'integer',
            'auto_apply_rewards' => 'boolean',
            'last_paid_item_price' => 'integer',
            'price_rise_notified_unit_price' => 'integer',
            'price_rise_notified_at' => 'datetime',
            'payment_attempt_count' => 'integer',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'oos_catchup_pending' => 'boolean',
            'locked_seller_discount_percent' => 'integer',
            'dogpack_first_order_percent_applied' => 'integer',
            'quantity' => 'integer',
            'frequency_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MarketplaceSubscription $subscription): void {
            if (empty($subscription->uuid)) {
                $subscription->uuid = (string) Str::uuid();
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function itemVariant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class);
    }

    public function lastPaidStoreOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'last_paid_store_order_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'subscription_id');
    }

    /**
     * Same ownership rule as My Orders: the account's own subscriptions, plus any
     * placed as a guest with the account's email.
     */
    public function scopeOwnedBy(Builder $query, mixed $user): Builder
    {
        return $query->where(function (Builder $owned) use ($user) {
            $owned->where('user_id', $user->id)
                ->orWhere('email', $user->email);
        });
    }

    /**
     * One lock per subscription, shared by renewals and every customer change, so a
     * card is never charged while the customer is cancelling or editing.
     */
    public function changeLock(): Lock
    {
        return Cache::lock('sns-subscription-'.$this->id, self::LOCK_SECONDS);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
