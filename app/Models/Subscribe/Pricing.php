<?php

namespace App\Models\Subscribe;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;

class Pricing extends Model
{
    use ConvertTimeZone;

    protected $table = 'pricings';

    protected $guarded = [];
}
