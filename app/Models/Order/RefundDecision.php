<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RefundDecision extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_REPLACED = 'replaced';

    public const STATUS_SETTLED = 'settled';

    public const TYPE_ITEM = 'item';

    public const TYPE_DELIVERY = 'delivery';

    public const TYPE_CANCELLATION = 'cancellation';

    protected $guarded = ['id'];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($decision) {
            $decision->uuid = Str::uuid();
        });
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }
}
