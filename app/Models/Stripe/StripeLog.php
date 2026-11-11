<?php

namespace App\Models\Stripe;

use Illuminate\Database\Eloquent\Model;

class StripeLog extends Model
{
    protected $table = 'stripe_logs';

    protected $fillable = [
        '*',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
