<?php

namespace App\Models\Product;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;

class ItemFacetMapping extends Model
{
    use ConvertTimeZone;

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $guarded = [];
}
