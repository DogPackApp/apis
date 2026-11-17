<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportProductVariant extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $fillable = [
        'uuid',
        '_id',
        'import_product_id',
        'option1_name',
        'option1_value',
        'option2_name',
        'option2_value',
        'option3_name',
        'option3_value',
        'price',
        'quantity',
        'country_of_origin',
        'sku',
        'weight',
        'unit',
        'continue_selling_when_out_of_stock',
        'hide_variant',
        'errors',
        'image',
    ];

    protected $casts = [
        'continue_selling_when_out_of_stock' => 'boolean',
        'errors' => 'array',
    ];

    // Relationship back to parent product
    public function product()
    {
        return $this->belongsTo(ImportProduct::class, 'import_product_id');
    }
}
