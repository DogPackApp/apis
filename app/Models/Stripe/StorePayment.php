<?php

namespace App\Models\Stripe;

use Illuminate\Database\Eloquent\Model;

class StorePayment extends Model
{
    const STATUS_PAID = 'paid';

    const STATUS_FAILED = 'failed';

    protected $guarded = [];
}
