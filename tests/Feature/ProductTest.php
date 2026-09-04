<?php

use App\Models\Product\Category;
use App\Models\Product\Item;
use App\Models\Seller\OnboardingStatus;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

function actingSeller(): array
{
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    return [$seller, $store];
}

function makeCategory(): Category
{
    return Category::query()->create([
        'name' => 'Toys',
        'slug' => 'toys',
        'ht_code' => '9503',
        'order' => '1',
        'is_active' => true,
    ]);
}

function activeProductPayload(Category $category, array $overrides = []): array
{
    return array_replace_recursive([
        'status' => 'ACTIVE',
        'about' => [
            'title' => 'Chew Toy',
            'type' => 'PHYSICAL',
            'description' => 'A durable chew toy.',
            'origin' => 'US',
        ],
        'price_inventory' => [
            'is_global_pricing' => false,
            'domestic_price' => 12.50,
            'global_price' => 15,
            'sku' => 'CHEW-1',
            'weight' => 1.5,
            'unit' => 'KG',
            'stock_quantity' => 5,
            'allow_overselling' => false,
        ],
        'category_tags' => [
            'category' => $category->uuid,
        ],
    ], $overrides);
}

test('product create requires authentication', function () {
    $this->postJson('/api/seller/product')->assertUnauthorized();
});

test('product create returns not found when seller has no store', function () {
    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/product', ['status' => 'DRAFT'])->assertNotFound();
});

test('seller can create an ACTIVE product with full validation', function () {
    [$seller, $store] = actingSeller();
    $category = makeCategory();

    $this->postJson('/api/seller/product', activeProductPayload($category))
        ->assertCreated();

    $item = Item::query()->where('store_id', $store->id)->firstOrFail();

    expect($item->title)->toBe('Chew Toy')
        ->and($item->slug)->toBe('chew-toy')
        ->and($item->status)->toBe(Item::STATUS[Item::STATUS_ACTIVE])
        ->and($item->category_id)->toBe($category->id);

    expect($item->price->domestic_price)->toBe(1250)
        ->and($item->price->global_price)->toBe(1500)
        ->and($item->price->weight)->toBe(150)
        ->and($item->price->stock_quantity)->toBe(5);

    $onboarding = OnboardingStatus::query()->where('seller_id', $seller->id)->first();
    expect($onboarding?->is_product)->toBe(1);
});

test('creating an ACTIVE product without required fields fails validation', function () {
    [, $store] = actingSeller();

    $this->postJson('/api/seller/product', ['status' => 'ACTIVE'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['about.title', 'about.type', 'about.description', 'about.origin', 'category_tags.category']);
});

test('creating a DRAFT product skips validation entirely', function () {
    [, $store] = actingSeller();
    $category = makeCategory();

    // is_global_pricing is a non-boolean string here, which the ACTIVE rules
    // (`required|boolean`) would reject — proves no validation runs at all
    // when status isn't ACTIVE, since the create still succeeds.
    $this->postJson('/api/seller/product', [
        'status' => 'DRAFT',
        'about' => [
            'title' => 'Draft Toy',
            'type' => 'PHYSICAL',
            'description' => 'Not finished yet.',
            'origin' => 'US',
        ],
        'price_inventory' => [
            'is_global_pricing' => 'not-a-boolean',
            'domestic_price' => 5,
            'global_price' => null,
            'stock_quantity' => 1,
            'weight' => 1,
        ],
        'category_tags' => ['category' => $category->uuid],
    ])->assertCreated();

    $item = Item::query()->where('title', 'Draft Toy')->firstOrFail();
    expect($item->status)->toBe(Item::STATUS[Item::STATUS_DRAFT])
        ->and($item->price->is_global_pricing)->toBeTrue();
});

test('creating a digital product nulls out stock quantity and weight', function () {
    actingSeller();
    $category = makeCategory();

    $this->postJson('/api/seller/product', activeProductPayload($category, [
        'about' => ['type' => 'DIGITAL'],
    ]))->assertCreated();

    $item = Item::query()->where('title', 'Chew Toy')->firstOrFail();

    expect($item->price->stock_quantity)->toBeNull()
        ->and($item->price->weight)->toBeNull();
});

test('product create returns 422 when the fallback category id does not exist', function () {
    actingSeller();

    $this->postJson('/api/seller/product', [
        'status' => 'DRAFT',
        'about' => [
            'title' => 'No Category',
            'type' => 'PHYSICAL',
            'description' => 'x',
            'origin' => 'US',
        ],
        'price_inventory' => ['is_global_pricing' => false, 'domestic_price' => 5],
    ])->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['message']]);
});

test('seller can list only their own store products', function () {
    [, $store] = actingSeller();
    $otherStore = Store::factory()->create();

    Item::factory()->for($store)->create(['title' => 'Mine']);
    Item::factory()->for($otherStore)->create(['title' => 'Not Mine']);

    $response = $this->getJson('/api/seller/product')->assertOk();

    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Mine')->not->toContain('Not Mine');
});

test('product list filters by status and type', function () {
    [, $store] = actingSeller();

    Item::factory()->for($store)->create(['title' => 'Active Physical', 'status' => Item::STATUS[Item::STATUS_ACTIVE], 'type' => Item::TYPES[Item::TYPE_PHYSICAL]]);
    Item::factory()->for($store)->create(['title' => 'Draft Digital', 'status' => Item::STATUS[Item::STATUS_DRAFT], 'type' => Item::TYPES[Item::TYPE_DIGITAL]]);

    $response = $this->getJson('/api/seller/product?status=ACTIVE')->assertOk();
    expect(collect($response->json('data'))->pluck('title')->values()->all())->toBe(['Active Physical']);

    $response = $this->getJson('/api/seller/product?type=DIGITAL')->assertOk();
    expect(collect($response->json('data'))->pluck('title')->values()->all())->toBe(['Draft Digital']);
});

test('seller can show a single product', function () {
    [, $store] = actingSeller();
    $item = Item::factory()->for($store)->create();

    $this->getJson("/api/seller/product/{$item->uuid}")
        ->assertOk()
        ->assertJsonPath('data.uuid', $item->uuid);
});

test('show returns 404 for another store product', function () {
    actingSeller();
    $otherStore = Store::factory()->create();
    $item = Item::factory()->for($otherStore)->create();

    $this->getJson("/api/seller/product/{$item->uuid}")->assertNotFound();
});

test('seller can update a product with ACTIVE validation', function () {
    [, $store] = actingSeller();
    $category = makeCategory();
    $item = Item::factory()->for($store)->create();

    $payload = activeProductPayload($category, [
        'about' => ['title' => 'Updated Title', 'is_description_edited' => false],
    ]);

    $this->putJson("/api/seller/product/{$item->uuid}", $payload)->assertOk();

    expect($item->refresh()->title)->toBe('Updated Title');
});

test('updating with non-ACTIVE status skips validation', function () {
    [, $store] = actingSeller();
    $category = makeCategory();
    $item = Item::factory()->for($store)->create();

    $this->putJson("/api/seller/product/{$item->uuid}", [
        'status' => 'DRAFT',
        'about' => ['title' => 'Draft Update', 'type' => 'PHYSICAL', 'description' => 'x', 'origin' => 'US'],
        'price_inventory' => ['is_global_pricing' => false, 'domestic_price' => 1, 'global_price' => null, 'stock_quantity' => 1, 'weight' => 1],
        'category_tags' => ['category' => $category->uuid],
    ])->assertOk();

    expect($item->refresh()->status)->toBe(Item::STATUS[Item::STATUS_DRAFT]);
});

test('update returns 404 for another store product', function () {
    actingSeller();
    $otherStore = Store::factory()->create();
    $item = Item::factory()->for($otherStore)->create();

    $this->putJson("/api/seller/product/{$item->uuid}", ['status' => 'DRAFT'])->assertNotFound();
});

test('archive toggle cycles active to archived to draft', function () {
    [, $store] = actingSeller();
    $item = Item::factory()->for($store)->create(['status' => Item::STATUS[Item::STATUS_ACTIVE]]);

    $this->putJson("/api/seller/product/{$item->uuid}/archive")->assertOk();
    expect($item->refresh()->status)->toBe(Item::STATUS[Item::STATUS_ARCHIVED]);

    $this->putJson("/api/seller/product/{$item->uuid}/archive")->assertOk();
    expect($item->refresh()->status)->toBe(Item::STATUS[Item::STATUS_DRAFT]);
});

test('archive toggle returns 404 for another store product', function () {
    actingSeller();
    $otherStore = Store::factory()->create();
    $item = Item::factory()->for($otherStore)->create();

    $this->putJson("/api/seller/product/{$item->uuid}/archive")->assertNotFound();
});
