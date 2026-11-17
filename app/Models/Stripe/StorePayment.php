<?php

namespace App\Models\Stripe;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;

class StorePayment extends Model
{
    use ConvertTimeZone;

    const STATUS_PAID = 'paid';

    const STATUS_FAILED = 'failed';

    protected $guarded = [];
}
