<?php

namespace App\Models\Review;

use App\Models\Concerns\ConvertTimeZone;
use App\Models\Order\StoreOrder;
use App\Models\Product\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ItemReview extends Model
{
    use ConvertTimeZone;

    protected $guarded = [];

    protected $casts = [
        'is_purchased' => 'boolean',
    ];

    const MODULE_ITEM = 'items';

    const MODULE_STORE = 'store';

    const MODULE_USER = 'user';

    const NOTIFICATION_TYPE = 'Marketplace_Review_Reply';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(DpUser::class, 'dp_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ItemReviewImage::class, 'item_review_id');
    }

    public function getIsVerifiedPurchaseAttribute($value)
    {
        if (is_numeric($value)) {
            return (bool) $value;
        }

        return (bool) $value;
    }
}
