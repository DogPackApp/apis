<?php

namespace App\Models\Order;

use App\Models\Concerns\ConvertTimeZone;
use App\Models\Product\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class ManualFulfillment extends Model
{
    use ConvertTimeZone;

    protected $fillable = [
        'uuid',
        'order_id',
        'store_order_id',
        'tracking_number',
        'tracking',
        'tracking_url',
        'carrier',
        'fulfilled_by',
        'notes',
    ];

    // Auto-generate UUID when creating a seller
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(
            Item::class,
            OrderItem::class,
            'store_order_id',
            'id',
            'store_order_id',
            'product_id',
        );
    }
}
