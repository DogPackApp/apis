<?php

namespace App\Models\Order;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Customers extends Model
{
    use ConvertTimeZone;

    public const PURCHASE_STATUS_ALLOWED = 'allowed';

    public const PURCHASE_STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'uuid',
        'store_order_id',
        'purchase_status',
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

    public function storeOrder(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class);
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
