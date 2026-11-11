<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;

class Avalara extends Model
{
    protected $table = 'avalaras';

    protected $fillable = [
        '*',
    ];
}
