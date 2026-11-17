<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ItemValueTranslation extends Model
{
    public $timestamps = false;

    public const TYPE_DESCRIPTION = 'description';

    public const TYPE_TITLE = 'title';

    protected $fillable = [
        'item_id',
        'type',
        'locale',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
        ];
    }
}
