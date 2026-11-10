<?php

namespace App\Services\Product;

use App\Models\Product\CategoryFacet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CategoryFacetService
{
    public function __construct(private CategoryService $categoryService) {}

    public function getCategoryFacetsWithValues(?int $categoryId, string $locale = 'en'): Collection
    {
        if ($categoryId) {
            $parentCategoryIds = $this->categoryService->getAllParentIds($categoryId);
            $parentCategoryIds = array_merge([$categoryId], $parentCategoryIds);
        } else {
            $parentCategoryIds = [];
        }

        $query = CategoryFacet::query()
            ->select([
                'id',
                'category_id',
                'title',
                'display_order',
            ])
            ->where('is_active', true)
            ->with('values:id,name,category_facet_id,display_order')
            ->whereHas('values')
            ->where(function ($q) use ($parentCategoryIds) {
                if (! empty($parentCategoryIds)) {
                    $q->whereIn('category_id', $parentCategoryIds)
                        ->orWhereNull('category_id');
                } else {
                    $q->whereNull('category_id');
                }
            });

        if ($parentCategoryIds !== [] && DB::getDriverName() === 'mysql') {
            $query->orderByRaw('FIELD(category_id, '.implode(',', $parentCategoryIds).')');
        }

        return $query->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }
}
