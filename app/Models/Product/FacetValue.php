<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacetValue extends Model
{
    protected $fillable = [
        'name',
        'category_facet_id',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function categoryFacet(): BelongsTo
    {
        return $this->belongsTo(CategoryFacet::class);
    }
}
