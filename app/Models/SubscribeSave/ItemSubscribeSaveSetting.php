<?php

namespace App\Models\SubscribeSave;

use App\Models\Product\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemSubscribeSaveSetting extends Model
{
    public const ADMIN_OVERRIDE_FORCE_ON = 'force_on';

    public const ADMIN_OVERRIDE_FORCE_OFF = 'force_off';

    protected $table = 'item_subscribe_save_settings';

    protected $fillable = [
        'item_id',
        'admin_override',
        'seller_disabled',
        'discount_percent',
    ];

    protected function casts(): array
    {
        return [
            'seller_disabled' => 'boolean',
            'discount_percent' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
