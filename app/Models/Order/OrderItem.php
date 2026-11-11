<?php

namespace App\Models\Order;

use App\Models\Concerns\ConvertTimeZone;
use App\Models\Product\AttributeOption;
use App\Models\Product\Discount;
use App\Models\Product\Item;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Str;

class OrderItem extends Model
{
    use ConvertTimeZone;

    protected $fillable = [
        'uuid',
        'store_order_id',
        'product_id',
        'is_subscribe_save',
        'subscription_id',
        'subscribe_save_seller_percent',
        'subscribe_save_dogpack_percent',
        'quantity',
        'original_price',
        'discount',
        'promo',
        'price',
        'tax',
        'subtotal',
        'refunded',
        'item_options',
        'product_snapshot',
        'currency_details',
    ];

    protected $casts = [
        'item_options' => 'array',
        'product_snapshot' => 'array',
        'currency_details' => 'array',
        'tax' => 'array',
        'promo' => 'array',
        'is_subscribe_save' => 'boolean',
        'subscribe_save_seller_percent' => 'integer',
        'subscribe_save_dogpack_percent' => 'integer',
    ];

    public function storeOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class);
    }

    public function marketplaceSubscription(): BelongsTo
    {
        return $this->belongsTo(MarketplaceSubscription::class, 'subscription_id');
    }

    public function product(): HasOne
    {
        return $this->hasOne(Item::class, 'id', 'product_id');
    }

    // the result why this is names as `discountRelation` instead of discount, because of hasOneThrough
    // relationship, it is conflicting with the existing discount method, and that's is why. I will fix this later
    // kindly don't follow this naming convention
    public function discountRelation(): HasOneThrough
    {
        return $this->hasOneThrough(
            Discount::class,  // Final model you want
            Item::class,      // Intermediate model
            'id',             // Foreign key on Item table (local_key on Items -> Item::class)
            'item_id',        // Foreign key on Discount table
            'product_id',     // Local key on Items table
            'id'              // Local key on Item table (to match Discount.item_id)
        );
    }

    // Auto-generate UUID when creating a seller
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }

    public function __attributeOptions($options): Collection|array
    {
        return AttributeOption::with('attribute')
            ->whereIn('id', array_values($options))
            ->get();
    }

    public function __itemVariant($options): ?ItemVariant
    {
        // Normalize once
        $selectedOptionIds = collect(array_map('intval', array_values($options)))->sort()->values()->toArray();

        // Eager-load once
        $variants = ItemVariant::with('attributesOption') // only id needed
            ->where('item_id', $this->product_id)
            ->get();

        // Compare normalized sets
        foreach ($variants as $variant) {
            $variantOptionIds = $variant->attributesOption->pluck('id')->sort()->values()->toArray();
            if ($variantOptionIds === $selectedOptionIds) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * Get Original Bill Amount
     */
    public function getOriginalPriceAmountAttribute(): float|int
    {
        return $this->original_price / 100;
    }

    /**
     * Get discount amount
     */
    public function getDiscountAmountAttribute(): float|int
    {
        return $this->discount / 100;
    }

    /**
     * Get final price of the product after discount
     */
    public function getFinalPriceAttribute(): float|int
    {
        return $this->subtotal / 100;
    }
}
