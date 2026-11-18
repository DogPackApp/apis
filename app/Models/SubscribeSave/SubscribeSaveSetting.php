<?php

namespace App\Models\SubscribeSave;

use Illuminate\Database\Eloquent\Model;

class SubscribeSaveSetting extends Model
{
    protected $table = 'subscribe_save_settings';

    protected $fillable = [
        'dogpack_first_order_percent',
        'allowed_seller_percents',
        'allowed_frequencies_days',
        'threshold_percent',
        'max_discount_amount',
    ];

    protected function casts(): array
    {
        return [
            'dogpack_first_order_percent' => 'integer',
            'allowed_seller_percents' => 'array',
            'allowed_frequencies_days' => 'array',
            'threshold_percent' => 'array',
            'max_discount_amount' => 'array',
        ];
    }
}
