<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

class ItemSection extends Model
{
    protected $table = 'item_section';

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'item_id',
        'section_id',
        'order',
    ];
}
