<?php

namespace App\Models\Order;

use App\Models\Product\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreOrder extends Model
{
    public static array $paymentStatus = [
        StoreOrder::ORDER_PAYMENT_STATUS_PENDING,
        StoreOrder::ORDER_PAYMENT_STATUS_FAILED,
        StoreOrder::ORDER_PAYMENT_STATUS_PAID,
        StoreOrder::ORDER_PAYMENT_STATUS_REFUNDED,
        StoreOrder::ORDER_PAYMENT_STATUS_PARTIALLY_REFUNDED,
    ];

    const ORDER_PAYMENT_STATUS_PENDING = 'pending';

    const ORDER_PAYMENT_STATUS_FAILED = 'failed';

    const ORDER_PAYMENT_STATUS_PAID = 'paid';

    const ORDER_PAYMENT_STATUS_REFUNDED = 'refunded';

    const ORDER_PAYMENT_STATUS_PARTIALLY_REFUNDED = 'partially-refunded';

    // #####
    public static array $fulfilmentStatus = [
        StoreOrder::ORDER_FULFILLED,
        StoreOrder::ORDER_CANCELLED,
        StoreOrder::ORDER_UNFULFILLED,
        StoreOrder::ORDER_PARTIALLY_FULFILLED,
    ];

    const ORDER_FULFILLED = 'fulfilled';

    const ORDER_CANCELLED = 'cancelled';

    const ORDER_UNFULFILLED = 'unfulfilled';

    const ORDER_PARTIALLY_FULFILLED = 'partially-fulfilled';

    // ####
    const ORDER_PM_STRIPE = 'pm_stripe';

    const ORDER_PM_PAYPAL = 'pm_paypal';

    const ORDER_PM_FREE = 'pm_free';

    const ORDER_PM_SUPERADMIN = 'superadmin';

    // ####
    public static array $orderStatus = [
        StoreOrder::ORDER_STATUS_DELIVERED,
        StoreOrder::ORDER_STATUS_OUT_FOR_DELIVERY,
        StoreOrder::ORDER_STATUS_SHIPPED,
        StoreOrder::ORDER_STATUS_PENDING,
        StoreOrder::ORDER_STATUS_CANCELLED,
        StoreOrder::ORDER_STATUS_MANUAL,
    ];

    const ORDER_STATUS_DELIVERED = 'delivered';

    const ORDER_STATUS_OUT_FOR_DELIVERY = 'out-for-delivery';

    const ORDER_STATUS_SHIPPED = 'shipped';

    const ORDER_STATUS_PENDING = 'pending';

    const ORDER_STATUS_CANCELLED = 'cancelled';

    const ORDER_STATUS_MANUAL = 'manual';

    const ORDER_COMMISSION = 7;

    const PAYOUT_DAYS = 14;

    const PAYOUT_IN = 28;

    const DELIVERY_EASYPOST = 'ep';

    const DELIVERY_FLAT = 'fr';

    const DIGITAL_DELIVERY = 'digital';

    const ADMIN_STATUS_DEFAULT = 0;

    const ADMIN_STATUS_NEED_APPROVAL = 1;

    const ADMIN_STATUS_REJECTED = 2;

    public const DISPUTE_FINISHED_STATUSES = [
        'won',
        'lost',
        'warning_closed',
        'charge_refunded',
    ];

    protected $fillable = [
        'uuid',
        'user_id',
        'store_id',
        'subscription_id',
        'payment_status',
        'order_status',
        'fulfilment_status',
        'fulfilment_date',
        'order_no',
        'shipping_method',
        'shipping',
        'source_id',
        'admin_status',
        'admin_reason',
        'dispute_status',
    ];

    protected $casts = [
        'shipping' => 'array',
        'admin_status' => 'integer',
    ];

    public function customer(): HasOne
    {
        return $this->hasOne(Customers::class);
    }

    public function orderBilling(): HasOne
    {
        return $this->hasOne(OrderBilling::class);
    }

    public function paymentDetail(): HasOne
    {
        return $this->hasOne(PaymentDetail::class);
    }

    public function disputeBlocksPayout(): bool
    {
        if ($this->dispute_status === null || $this->dispute_status === '') {
            return false;
        }

        return ! in_array($this->dispute_status, self::DISPUTE_FINISHED_STATUSES, true);
    }

    public function disputeApiStatus(): ?string
    {
        if ($this->dispute_status === null || $this->dispute_status === '') {
            return null;
        }

        if ($this->disputeBlocksPayout()) {
            return 'ongoing';
        }

        return $this->dispute_status === 'won' ? 'won' : 'closed';
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function marketplaceSubscription(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSubscription::class, 'subscription_id');
    }

    /**
     * Fetch shopify products in order
     */
    public function shopifyProducts(): HasManyThrough
    {
        return $this->hasManyThrough(
            Item::class,       // Final model
            OrderItem::class,  // Intermediate model
            'store_order_id',  // Foreign key on order_items table...
            'id',              // Foreign key on items table...
            'id',              // Local key on store_orders table...
            'product_id'       // Local key on order_items table...
        )->whereNotNull('items.source_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Get total refunded amount of the order
     */
    public function getStoreOrderTotalRefundAmount(): float
    {
        return $this->refunds()
            ->select(DB::raw('ROUND(SUM(converted + tax_converted) / 100, 2) as total'))
            ->value('total');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * The seller can cancel the order anytime until the payout has been sent to their stripe account
     * until that, they can purchase and void multiple times
     * so if the refund_status is null that means that is the latest rate being used
     * if the refund_status = EasyPost::CANCELLED then the shipping label was bought
     * and voided later for whatever reason
     * This information we need when we are calculating the payout amount for a particular order
     */
    public function easypost(): HasOne
    {
        return $this->hasOne(EasyPost::class)
            ->whereNull('refund_status')
            ->latest();
    }

    public function easyposts(): HasMany
    {
        return $this->hasMany(EasyPost::class)
            ->whereIn('refund_status', [EasyPost::CANCELLED, EasyPost::VOIDED]);
    }

    public function manualFulfillment(): HasOne
    {
        return $this->hasOne(ManualFulfillment::class)->latest();
    }

    // Auto-generate UUID when creating a seller
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }

    public function timelines(): HasMany
    {
        return $this->hasMany(Timeline::class);
    }

    public function avalara(): HasOne
    {
        return $this->hasOne(Avalara::class);
    }

    public static function __fetchByOrderNo($orderNo): Model|Builder|null
    {
        return StoreOrder::query()
            ->where('order_no', $orderNo)
            ->first();
    }

    public function payoutDetails(): HasOne
    {
        return $this->hasOne(PayoutDetail::class, 'store_order_id')->oldestOfMany();
    }

    public function getFlatShippingRateForShopAttribute()
    {
        return (float) ($this->shipping['original']['price'] ?? 0);
    }

    public function getEasyPostShippingRateForShopAttribute()
    {
        return (float) ($this->shipping['origin']['price'] ?? 0);
    }

    public function getShippingRate()
    {
        return $this->shipping_method == StoreOrder::DELIVERY_EASYPOST
            ? $this->easy_post_shipping_rate_for_shop
            : $this->flat_shipping_rate_for_shop;
    }

    public function __orderTotalQuantity(): int
    {
        $this->loadMissing('items:id,store_order_id,quantity');

        return (int) $this->items->sum('quantity');
    }

    public function getBuyerCurrency(): string
    {
        return strtoupper($this->orderBilling->currency_details['currency'] ?? '');
    }

    public function getBuyerUserId(): ?int
    {
        $userId = $this->user_id ?? $this->customer?->user_id;

        return $userId !== null ? (int) $userId : null;
    }

    public function getBuyerEmail(): ?string
    {
        $email = strtolower(trim($this->customer?->email ?? ''));

        return $email !== '' ? $email : null;
    }

    public function getBuyerSubtotal(): float
    {
        return (float) ($this->orderBilling->currency_details['subtotal'] ?? 0);
    }

    public function getBuyerCartTotal(): float
    {
        return (float) ($this->orderBilling->currency_details['total'] ?? 0);
    }

    public function getBuyerDelivery(): float
    {
        return (float) ($this->orderBilling->currency_details['delivery'] ?? 0);
    }

    /**
     * Buyer-currency wallet discount applied to this store's merchandise.
     */
    public function getBuyerWalletDiscountAmount(): float
    {
        $wallet = is_array($this->orderBilling?->wallet) ? $this->orderBilling->wallet : [];
        if (array_key_exists('discount_amount', $wallet)) {
            return (float) $wallet['discount_amount'];
        }

        return ((int) ($this->orderBilling?->wallet_discount ?? 0)) / 100;
    }

    /**
     * Cashback base: merchandise subtotal after wallet discount, excluding tax and delivery.
     */
    public function getBuyerCashbackBase(): float
    {
        return max(0.0, round($this->getBuyerSubtotal() - $this->getBuyerWalletDiscountAmount(), 2));
    }

    /**
     * Card-paid cents for this line after attributing wallet discount in the
     * same ratio as cashback scale (line merch / billing subtotal).
     */
    public function cardRefundCentsForLineMerchandise(float $lineMerchandise): int
    {
        $lineCents = (int) round($lineMerchandise * 100);
        if ($lineCents <= 0) {
            return 0;
        }

        $subtotal = $this->getBuyerSubtotal();
        $walletDiscount = $this->getBuyerWalletDiscountAmount();

        if ($subtotal <= 0 || $walletDiscount <= 0) {
            return $lineCents;
        }

        $walletShareCents = (int) round(($lineMerchandise / $subtotal) * $walletDiscount * 100);

        return max(0, $lineCents - $walletShareCents);
    }

    /**
     * Scale refunded/cancelled line merchandise onto the same wallet-discounted
     * cashback base used at award, so a 1-of-2 refund does not reverse as if
     * there was no wallet discount.
     */
    public function scaleLineMerchandiseToCashbackBase(float $lineMerchandise): float
    {
        if ($lineMerchandise <= 0) {
            return 0.0;
        }

        $subtotal = $this->getBuyerSubtotal();
        $cashbackBase = $this->getBuyerCashbackBase();

        if ($subtotal <= 0) {
            return round($lineMerchandise, 2);
        }

        if ($cashbackBase <= 0) {
            return 0.0;
        }

        if ($cashbackBase >= $subtotal) {
            return round($lineMerchandise, 2);
        }

        return round($lineMerchandise * ($cashbackBase / $subtotal), 2);
    }

    protected static function booted()
    {
        static::addGlobalScope('admin_status_default', function ($query) {
            $query->where('admin_status', self::ADMIN_STATUS_DEFAULT);
        });
    }
}
