<?php

namespace App\Models\Misc;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'name',
    ];
}
