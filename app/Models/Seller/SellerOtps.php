<?php

namespace App\Models\Seller;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SellerOtps extends Model
{
    protected $fillable = [
        'uuid',
        'seller_id',
        'otp',
        'is_active',
        'login_type',
    ];

    protected function casts(): array
    {
        return [
            'otp' => 'integer',
            'is_active' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SellerOtps $sellerOtp): void {
            $sellerOtp->uuid ??= (string) Str::uuid();
        });
    }
}
