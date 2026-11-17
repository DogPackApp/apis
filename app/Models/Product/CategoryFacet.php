<?php

namespace App\Models\Product;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryFacet extends Model
{
    use ConvertTimeZone;

    protected $fillable = [
        'title',
        'category_id',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(FacetValue::class)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(FacetTranslation::class, 'entity_id', 'id')
            ->where('entity_type', FacetTranslation::ENTITY_TYPE_CATEGORY_FACET);
    }
}
