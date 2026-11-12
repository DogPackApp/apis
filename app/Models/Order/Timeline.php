<?php

namespace App\Models\Order;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Timeline extends Model
{
    use ConvertTimeZone;

    // Order Events
    const ORDER_PLACED = 'order.placed';

    const ORDER_EMAIL = 'order.email';

    const ORDER_CANCELLED = 'order.cancelled';

    const ORDER_COMPLETED = 'order.completed';

    // TODO: Email we need to send after 10 days
    const ORDER_EMAIL_REVIEW = 'order.email.review';

    // Refund Events
    const REFUND_FULL_INITIATED = 'refund.full.initiated';

    const REFUND_PARTIAL_INITIATED = 'refund.partial.initiated';

    const REFUND_EMAIL = 'refund.email';

    // Payment Events
    const PAYMENT_CONFIRMED = 'payment.confirmed';

    // Fulfillment Events
    const FULFILLMENT_COMPLETED = 'fulfillment.completed';

    const FULFILLMENT_VOIDED = 'fulfillment.voided';

    const FULFILLMENT_MANUAL_COMPLETED = 'fulfillment.manual.completed';

    const FULFILLMENT_MANUAL_TRACKING = 'fulfillment.manual.tracking';

    const FULFILLMENT_MANUAL_TRACKING_EMAIL = 'fulfillment.manual.tracking.email';

    const FULFILLMENT_EMAIL = 'fulfillment.email';

    // Payout Events
    const PAYOUT_CREATED = 'payout.created';

    /**
     * Get all events as an array
     */
    public static function list(): array
    {
        return [
            self::ORDER_PLACED,
            self::ORDER_EMAIL,
            self::ORDER_CANCELLED,
            self::REFUND_FULL_INITIATED,
            self::REFUND_PARTIAL_INITIATED,
            self::REFUND_EMAIL,
            self::PAYMENT_CONFIRMED,
            self::FULFILLMENT_COMPLETED,
            self::FULFILLMENT_VOIDED,
            self::FULFILLMENT_MANUAL_COMPLETED,
            self::FULFILLMENT_MANUAL_TRACKING,
            self::FULFILLMENT_MANUAL_TRACKING_EMAIL,
            self::FULFILLMENT_EMAIL,
            self::ORDER_COMPLETED,
            self::ORDER_EMAIL_REVIEW,
            self::PAYOUT_CREATED,
        ];
    }

    protected $fillable = [
        'event',
        'store_order_id',
        'additional_data',
        'email',
        'created_at',
    ];

    // Auto-generate UUID when creating a seller
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }
}
