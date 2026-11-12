<?php

namespace App\Models\Misc;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use ConvertTimeZone;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'name',
    ];
}
