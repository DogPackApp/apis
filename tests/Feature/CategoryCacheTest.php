<?php

use App\Models\Product\Category;
use App\Services\Product\CategoryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('a missing category cache is stored on this apps own key', function () {
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
        ->and($stored)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class)
        ->and($stored->first())->toBeInstanceOf(Category::class)
        ->and(Cache::has('website:marketplace:categories:all_categories_flat.en'))->toBeFalse();
});

test('category structure ignores the shared key and caches its own copy', function () {
    Category::query()->create([
        'name' => 'Toys',
        'slug' => 'toys',
        'ht_code' => '9503',
        'order' => '1',
        'is_active' => true,
    ]);

    $sharedKey = 'website:marketplace:categories:categories_seller_structure.en';
    $otherAppModel = unserialize('O:31:"App\\Models\\Marketplace\\Category":0:{}', ['allowed_classes' => false]);
    Cache::put($sharedKey, collect([$otherAppModel]), 86400);

    $categories = app(CategoryService::class)->getCategoriesSellerStructure();
    $stored = Cache::get($sharedKey.'.apis');

    expect($categories->first())->toBeInstanceOf(Category::class)
        ->and($categories->first()->name)->toBe('Toys')
        ->and($categories->first()->full_path)->toBe('Toys')
        ->and($categories->first()->is_active)->toBe(1)
        ->and($stored->first())->toBeInstanceOf(Category::class)
        ->and(Cache::get($sharedKey))->toBeInstanceOf(Collection::class);
});
