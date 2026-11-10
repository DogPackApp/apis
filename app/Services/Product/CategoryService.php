<?php

namespace App\Services\Product;

use App\Models\Product\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryService
{
    public function getAllCategories(string $language = 'en'): Collection
    {
        $cachekeyLanguage = 'website:marketplace:categories:all_categories_flat.'.$language;

        return Cache::remember($cachekeyLanguage, 86400, function () use ($language) {
            if ($language === 'en') {
                return Category::where('is_active', 1)
                    ->orderBy('id')
                    ->get();
            }

            return Category::where('categories.is_active', 1)
                ->leftJoin('categories_translation as ct_name', function ($join) use ($language) {
                    $join->on('categories.id', '=', 'ct_name.categories_id')
                        ->where('ct_name.lang_type', '=', $language)
                        ->where('ct_name.type', '=', 'category');
                })
                ->leftJoin('categories_translation as ct_tag', function ($join) use ($language) {
                    $join->on('categories.id', '=', 'ct_tag.categories_id')
                        ->where('ct_tag.lang_type', '=', $language)
                        ->where('ct_tag.type', '=', 'tag');
                })
                ->selectRaw('categories.*,
                    COALESCE(ct_name.title, categories.name) as name,
                    COALESCE(ct_tag.title, categories.tag) as tag')
                ->orderBy('categories.id')
                ->get();
        });
    }

    public function getCategoriesSellerStructure(string $language = 'en'): Collection
    {
        return Cache::remember('website:marketplace:categories:categories_seller_structure.'.$language, 86400, function () use ($language) {
            $allCategories = $this->getAllCategories($language);

            return $allCategories->map(function ($category) use ($allCategories) {
                $parentNames = $this->getParentNames($category, $allCategories);
                $category->title = $category->name;
                $category->parents = $parentNames;
                $category->top_parent = $parentNames[0];
                $category->full_path = implode(' > ', $parentNames);

                return $category;
            });
        });
    }

    /**
     * @return array<int, int>
     */
    public function getAllParentIds(int $categoryId): array
    {
        $allCategories = $this->getAllCategories();
        $category = $allCategories->firstWhere('id', $categoryId);
        $parentIds = [];
        $curr = $category->parent_id;
        while ($curr) {
            $currParent = $allCategories->where('id', $curr)->first();
            $parentIds[] = $currParent->id;
            $curr = $currParent->parent_id;
        }

        return array_reverse($parentIds);
    }

    /**
     * @return array<int, string>
     */
    protected function getParentNames(Category $category, Collection $allCategories): array
    {
        $parentNames = [$category->name];
        $curr = $category->parent_id;
        while ($curr) {
            $currParent = $allCategories->where('id', $curr)->first();
            $parentNames[] = $currParent->name;
            $curr = $currParent->parent_id;
        }

        return array_reverse($parentNames);
    }
}
