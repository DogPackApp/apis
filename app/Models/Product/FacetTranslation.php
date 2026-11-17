<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class FacetTranslation extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    public const ENTITY_TYPE_CATEGORY_FACET = 'title';

    public const ENTITY_TYPE_FACET_VALUE = 'value';

    protected $fillable = [
        'entity_id',
        'entity_type',
        'locale',
        'text',
    ];
}
