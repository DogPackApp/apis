<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Refund extends Model
{
    protected $guarded = ['id'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }

    public function decision(): HasOne
    {
        return $this->hasOne(RefundDecision::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function storeOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class);
    }

    public function __originalRefund(): ?Refund
    {
        $this->loadMissing('storeOrder:id,order_no');
        if ($this->storeOrder?->order_no) {
            $refundDecision = RefundDecision::where('replacement_order_no', $this->storeOrder->order_no)->with('refund')->first();

            return $refundDecision?->refund ?? null;
        }

        return null;
    }
}
