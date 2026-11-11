<?php

namespace App\Models\Stripe;

use Illuminate\Database\Eloquent\Model;

class DisputeStripeLog extends Model
{
    protected $table = 'dispute_stripe_logs';

    protected $fillable = [
        'stripe_event_id',
        'event_type',
        'dispute_id',
        'payment_intent_id',
        'charge_id',
        'amount',
        'currency',
        'reason',
        'status',
        'store_order_ids',
        'payload',
    ];

    protected $casts = [
        'store_order_ids' => 'array',
        'payload' => 'array',
    ];
}
