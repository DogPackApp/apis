<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentDetail extends Model
{
    protected $fillable = [
        'uuid',
        'payment_id',
        'transaction_id',
        'payment_method',
        'card',
        'risk_level',
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
