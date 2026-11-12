<?php

namespace App\Models\Store;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;

class ItemSection extends Model
{
    use ConvertTimeZone;

    protected $table = 'item_section';

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'item_id',
        'section_id',
        'order',
    ];
}
