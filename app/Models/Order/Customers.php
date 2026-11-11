<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Customers extends Model
{
    protected $fillable = [
        'uuid',
        'store_order_id',
        'name',
        'email',
        'phone',
        'shipping_address',
        'billing_address',
    ];

    protected $casts = [
        'shipping_address' => 'array',
        'billing_address' => 'array',
    ];

    // Auto-generate UUID when creating a seller
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }

    /**
     * Get first name of the customer
     *
     * @return string
     */
    public function getFirstNameAttribute()
    {
        $parts = explode(' ', $this->name);

        return $parts[0] ?? '';
    }

    /**
     * Get Last name of the customer
     *
     * @return string
     */
    public function getLastNameAttribute()
    {
        $parts = explode(' ', $this->name);

        return $parts[1] ?? '';
    }
}
