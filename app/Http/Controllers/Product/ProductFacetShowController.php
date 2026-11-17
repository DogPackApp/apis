<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\Product\ProductFacetShowResource;
use App\Models\Product\Category;
use App\Services\Product\CategoryFacetService;
use App\Services\Product\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductFacetShowController extends Controller
{
    public function __construct(
        protected CategoryFacetService $categoryFacetService,
        protected CategoryService $categoryService,
    ) {}

    public function __invoke(Request $request, Category $category): AnonymousResourceCollection|JsonResponse
    {
        $language = $request->header('X-localization') ?? 'en';
        $categories = $this->categoryFacetService->getCategoryFacetsWithValues($category->id, $language);

        return ProductFacetShowResource::collection($categories);
    }
}
