<?php

namespace App\Models\Order;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EasyPost extends Model
{
    use ConvertTimeZone;

    public const UNKNOWN = 'unknown';

    public const PRE_TRANSIT = 'pre_transit';

    public const IN_TRANSIT = 'in_transit';

    public const OUT_FOR_DELIVERY = 'out_for_delivery';

    public const AVAILABLE_FOR_PICKUP = 'available_for_pickup';

    public const DELIVERED = 'delivered';

    public const RETURN_TO_SENDER = 'return_to_sender';

    public const FAILURE = 'failure';

    // when the EP was voided after it was scanned we use this status
    public const CANCELLED = 'cancelled';

    // When the EP was voided before it was scanned we use this status
    public const VOIDED = 'VOIDED';

    public const ERROR = 'error';

    public const RE_SUBMITTED = 'submitted';

    public const RE_REFUNDED = 'refunded';

    public const RE_REJECTED = 'rejected';

    public const RE_NOT_APPLICABLE = 'not_applicable';

    protected $fillable = [
        '*',
    ];

    // Auto-generate UUID when creating a seller
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    protected $casts = [
        'shipment' => 'array',
        'rates' => 'array',
    ];
}
