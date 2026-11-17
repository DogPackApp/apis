<?php

namespace App\Models\Stripe;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;

class StripeLog extends Model
{
    use ConvertTimeZone;

    protected $table = 'stripe_logs';

    protected $fillable = [
        '*',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
