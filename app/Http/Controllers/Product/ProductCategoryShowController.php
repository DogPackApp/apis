<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\Product\ProductLocalizedCategoryResource;
use App\Services\Product\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\App;

class ProductCategoryShowController extends Controller
{
    public function __construct(protected CategoryService $categoryService) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $language = $request->header('X-localization') ?? 'en';
        App::setLocale($language);
        $categories = $this->categoryService->getCategoriesSellerStructure($language);

        return ProductLocalizedCategoryResource::collection($categories);
    }
}
