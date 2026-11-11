<?php

namespace App\Models\Store;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ShippingAddress extends Model
{
    use ConvertTimeZone;
    use SoftDeletes;

    protected $fillable = [
        'store_id',
        'easypost_id',
        'address',
        'street1',
        'street2',
        'zipcode',
        'city',
        'country',
        'latitude',
        'longitude',
        'province',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (ShippingAddress $address): void {
            $address->uuid ??= (string) Str::uuid();
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
