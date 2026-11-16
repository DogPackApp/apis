<?php

namespace App\Services\Product;

use App\Models\Product\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CategoryService
{
    public function getAllCategories(string $language = 'en'): Collection
    {
        $cachekeyLanguage = 'website:marketplace:categories:all_categories_flat.'.$language;

        return $this->rememberCategories($cachekeyLanguage, function () use ($language) {
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
        return $this->rememberCategories('website:marketplace:categories:categories_seller_structure.'.$language, function () use ($language) {
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
     * @return array<int, int>
     */
    public function getAllChildIds(int $categoryId, string $language = 'en'): array
    {
        return $this->getChildIds($categoryId, $this->getAllCategories($language));
    }

    /**
     * @return array<int, int>
     */
    private function getChildIds(int $categoryId, Collection $allCategories): array
    {
        $allChildIds = [];
        $currentLevelIds = [$categoryId];

        while ($currentLevelIds !== []) {
            $nextLevelIds = $allCategories
                ->whereIn('parent_id', $currentLevelIds)
                ->pluck('id')
                ->all();

            if ($nextLevelIds === []) {
                break;
            }

            $allChildIds = array_merge($allChildIds, $nextLevelIds);
            $currentLevelIds = $nextLevelIds;
        }

        return $allChildIds;
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

    /**
     * Backend-api stores Eloquent models in the shared keys. This app's cache
     * store will not unserialize those models, so a miss or a bad value is
     * rebuilt as attribute rows under a separate key.
     *
     * @param  callable(): Collection<int, Category>  $resolve
     * @return Collection<int, Category>
     */
    private function rememberCategories(string $key, callable $resolve): Collection
    {
        try {
            $shared = Cache::get($key);

            if ($shared instanceof Collection && $this->categoriesBelongToThisApp($shared)) {
                return $shared;
            }
        } catch (Throwable) {
            // The shared value was serialized for the other application.
        }

        $ownKey = $key.'.apis';

        try {
            $own = Cache::get($ownKey);
            $rows = is_array($own) && ($own['category_cache'] ?? null) === 1 && is_array($own['rows'] ?? null)
                ? $own['rows']
                : null;

            if (is_array($rows)) {
                return Category::hydrate($rows);
            }
        } catch (Throwable) {
            // The previous copy was not readable either.
        }

        $categories = $resolve();

        Cache::put($ownKey, [
            'category_cache' => 1,
            'rows' => $categories->map(fn (Category $category) => $category->getAttributes())->values()->all(),
        ], 86400);

        return $categories;
    }

    private function categoriesBelongToThisApp(Collection $categories): bool
    {
        foreach ($categories as $category) {
            if (! $category instanceof Category) {
                return false;
            }
        }

        return true;
    }
}
