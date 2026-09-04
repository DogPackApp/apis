<?php

namespace App\Models\Misc;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'short_name',
        'name',
        'currency_code',
        'currency_name',
        'currency_symbol',
        'is_display',
        'is_display_front',
        'is_display_mobile',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'is_display' => 'integer',
            'is_display_front' => 'integer',
            'is_display_mobile' => 'integer',
            'order' => 'integer',
        ];
    }
}
