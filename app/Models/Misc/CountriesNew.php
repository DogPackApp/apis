<?php

namespace App\Models\Misc;

use Illuminate\Database\Eloquent\Model;

class CountriesNew extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'countries_new';

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'short_name',
        'name',
    ];
}
