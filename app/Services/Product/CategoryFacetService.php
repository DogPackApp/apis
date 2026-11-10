<?php

namespace App\Services\Product;

use App\Jobs\Product\FacetTranslationJob;
use App\Models\Product\CategoryFacet;
use App\Models\Product\FacetTranslation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CategoryFacetService
{
    public function __construct(
        private CategoryService $categoryService,
        private GoogleTranslateService $googleTranslateService,
    ) {}

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
                if ($parentCategoryIds !== []) {
                    $q->whereIn('category_id', $parentCategoryIds)
                        ->orWhereNull('category_id');
                } else {
                    $q->whereNull('category_id');
                }
            });

        if ($locale !== 'en') {
            $query->with([
                'translations' => function ($query) use ($locale) {
                    $query->where('locale', $locale);
                },
                'values.translations' => function ($query) use ($locale) {
                    $query->where('locale', $locale);
                },
            ]);
        }

        if ($parentCategoryIds !== [] && DB::getDriverName() === 'mysql') {
            $query->orderByRaw('FIELD(category_id, '.implode(',', $parentCategoryIds).')');
        }

        $categoryFacets = $query->orderBy('display_order')
            ->orderBy('id')
            ->get();

        return $this->translateCategoryFacets($categoryFacets, $locale);
    }

    private function translateCategoryFacets(Collection $categoryFacets, string $locale): Collection
    {
        if ($locale === 'en' || $categoryFacets->isEmpty()) {
            return $categoryFacets;
        }

        $textsToTranslate = [];
        $indexMap = [];

        foreach ($categoryFacets as $categoryFacet) {
            $facetTranslation = $categoryFacet->translations->first();
            if ($facetTranslation) {
                $categoryFacet->title = $facetTranslation->text;
            } elseif (! empty($categoryFacet->title)) {
                $batchIndex = count($textsToTranslate);
                $textsToTranslate[] = $categoryFacet->title;
                $indexMap[$batchIndex] = [
                    'entity_id' => $categoryFacet->id,
                    'entity_type' => FacetTranslation::ENTITY_TYPE_CATEGORY_FACET,
                ];
            }

            foreach ($categoryFacet->values as $value) {
                $valueTranslation = $value->translations->first();
                if ($valueTranslation) {
                    $value->name = $valueTranslation->text;
                } elseif (! empty($value->name)) {
                    $batchIndex = count($textsToTranslate);
                    $textsToTranslate[] = $value->name;
                    $indexMap[$batchIndex] = [
                        'entity_id' => $value->id,
                        'entity_type' => FacetTranslation::ENTITY_TYPE_FACET_VALUE,
                    ];
                }
            }
        }

        if ($textsToTranslate === []) {
            return $categoryFacets;
        }

        try {
            $translatedTexts = $this->googleTranslateService->translateBatch(
                $textsToTranslate,
                $locale,
                'en'
            );

            $entries = [];
            foreach ($indexMap as $batchIndex => $meta) {
                if (empty($translatedTexts[$batchIndex])) {
                    continue;
                }

                $translatedText = $translatedTexts[$batchIndex];
                if ($meta['entity_type'] === FacetTranslation::ENTITY_TYPE_CATEGORY_FACET) {
                    $categoryFacets->firstWhere('id', $meta['entity_id'])->title = $translatedText;
                } else {
                    foreach ($categoryFacets as $categoryFacet) {
                        $value = $categoryFacet->values->firstWhere('id', $meta['entity_id']);
                        if ($value) {
                            $value->name = $translatedText;
                            break;
                        }
                    }
                }

                $entries[] = [
                    'entity_id' => $meta['entity_id'],
                    'entity_type' => $meta['entity_type'],
                    'locale' => $locale,
                    'text' => $translatedText,
                ];
            }

            if ($entries !== []) {
                FacetTranslationJob::dispatch($entries);
            }
        } catch (Throwable $e) {
            Log::error('Failed to translate category facets: '.$e->getMessage(), [
                'locale' => $locale,
                'facets_count' => $categoryFacets->count(),
            ]);
        }

        return $categoryFacets;
    }
}
