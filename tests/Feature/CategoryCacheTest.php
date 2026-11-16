<?php

use App\Models\Product\Category;
use App\Services\Product\CategoryService;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('a missing category cache is stored as attribute rows this app can read', function () {
    $category = Category::query()->create([
        'name' => 'Toys',
        'slug' => 'toys',
        'ht_code' => '9503',
        'order' => '14',
        'is_active' => true,
    ]);

    $categories = app(CategoryService::class)->getAllCategories();
    $stored = Cache::get('website:marketplace:categories:all_categories_flat.en.apis');

    expect($categories->first()->is($category))->toBeTrue()
        ->and($categories->first()->is_active)->toBe(1)
        ->and($categories->first()->order)->toBe(14)
        ->and($stored['category_cache'])->toBe(1)
        ->and(Cache::has('website:marketplace:categories:all_categories_flat.en'))->toBeFalse();
});

test('an unreadable category cache is rebuilt into this apps own key', function () {
    Category::query()->create([
        'name' => 'Toys',
        'slug' => 'toys',
        'ht_code' => '9503',
        'order' => '1',
        'is_active' => true,
    ]);

    $otherAppModel = unserialize('O:31:"App\\Models\\Marketplace\\Category":0:{}');
    Cache::put('website:marketplace:categories:categories_seller_structure.en', collect([$otherAppModel]), 86400);

    $categories = app(CategoryService::class)->getCategoriesSellerStructure();

    expect($categories->first())->toBeInstanceOf(Category::class)
        ->and($categories->first()->name)->toBe('Toys')
        ->and($categories->first()->full_path)->toBe('Toys')
        ->and($categories->first()->is_active)->toBe(1)
        ->and(Cache::get('website:marketplace:categories:categories_seller_structure.en.apis')['category_cache'])->toBe(1);
});
