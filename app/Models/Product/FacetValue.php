<?php

namespace App\Models\Product;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacetValue extends Model
{
    use ConvertTimeZone;

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

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_facet_mappings');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(FacetTranslation::class, 'entity_id', 'id')
            ->where('entity_type', FacetTranslation::ENTITY_TYPE_FACET_VALUE);
    }
}
