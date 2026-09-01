<?php

namespace App\Models\Seller;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OnboardingStatus extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'seller_id',
        'store_id',
        'is_product',
        'is_shipping',
        'is_store_setting',
        'is_finance',
        'is_subscribe',
    ];

    protected function casts(): array
    {
        return [
            'is_product' => 'integer',
            'is_shipping' => 'integer',
            'is_store_setting' => 'integer',
            'is_finance' => 'integer',
            'is_subscribe' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (OnboardingStatus $onboarding): void {
            $onboarding->uuid ??= (string) Str::uuid();
        });
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
