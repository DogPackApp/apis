<?php

namespace App\Models\Order;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class ManualFulfillment extends Model
{
    use ConvertTimeZone;

    protected $fillable = [
        'uuid',
        'order_id',
        'tracking_number',
        'tracking',
        'tracking_url',
        'carrier',
        'fulfilled_by',
        'notes',
    ];

    // Auto-generate UUID when creating a seller
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_tags', 'tags_id', 'item_id');
    }
}
