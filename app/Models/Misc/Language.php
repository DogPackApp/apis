<?php

namespace App\Models\Misc;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'name',
        'short_name',
        'is_front',
        'is_app',
        'is_admin',
    ];

    protected function casts(): array
    {
        return [
            'is_front' => 'integer',
            'is_app' => 'integer',
            'is_admin' => 'integer',
        ];
    }
}
