<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class CategoryTranslation extends Model
{
    public $timestamps = false;

    protected $table = 'categories_translation';

    protected $fillable = [
        'categories_id',
        'language_id',
        'lang_type',
        'title',
        'type',
    ];
}
