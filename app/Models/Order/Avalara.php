<?php

namespace App\Models\Order;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;

class Avalara extends Model
{
    use ConvertTimeZone;

    protected $table = 'avalaras';

    protected $fillable = [
        '*',
    ];
}
