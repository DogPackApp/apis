<?php

use App\Jobs\Product\ExportProductsJob;
use App\Mail\Product\ProductsExportEmail;
use App\Models\Product\Category;
use App\Models\Product\CategoryFacet;
use App\Models\Product\CategoryTranslation;
use App\Models\Product\ExportProduct;
use App\Models\Product\FacetTranslation;
use App\Models\Product\FacetValue;
use App\Models\Product\Gallery;
use App\Models\Product\Item;
use App\Models\Product\ItemMedia;
use App\Models\Product\ItemPrice;
use App\Models\Seller\OnboardingStatus;
use App\Models\Seller\Seller;
use App\Models\Store\Policy;
use App\Models\Store\Section;
use App\Models\Store\Store;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

test('creating a product stores the selected policy', function () {
    [, $store] = actingSeller();
    $category = makeCategory();
    $policy = new Policy;
    $policy->store_id = $store->id;
    $policy->name = 'Standard';
    $policy->save();

    $this->postJson('/api/seller/product', activeProductPayload($category, [
        'policy' => (string) $policy->uuid,
    ]))->assertCreated();

    expect(Item::query()->where('store_id', $store->id)->first()->policy_id)->toBe($policy->id);
});

test('creating a product attaches tags and sections', function () {
    [, $store] = actingSeller();
    $category = makeCategory();
    $section = new Section;
    $section->store_id = $store->id;
    $section->name = 'Toys';
    $section->slug = 'toys';
    $section->is_visible = 1;
    $section->order = 1;
    $section->save();

    $this->postJson('/api/seller/product', activeProductPayload($category, [
        'category_tags' => [
            'category' => $category->uuid,
            'tags' => ['Chew', 'Outdoor'],
        ],
        'section' => [(string) $section->uuid],
    ]))->assertCreated();

    $item = Item::query()->where('store_id', $store->id)->first();

    expect($item->tags()->pluck('name')->sort()->values()->all())->toBe(['Chew', 'Outdoor'])
        ->and($item->sections()->pluck('slug')->sort()->values()->all())->toBe(['all', 'toys']);

    $this->putJson('/api/seller/product/'.$item->uuid, activeProductPayload($category, [
        'about' => ['is_description_edited' => false],
        'category_tags' => [
            'category' => $category->uuid,
            'tags' => ['Chew'],
        ],
        'section' => [],
    ]))->assertOk();

    expect($item->tags()->pluck('name')->all())->toBe(['Chew'])
        ->and($item->sections()->pluck('slug')->all())->toBe(['all']);
});

test('bulk move sends invalid products back and drafts the rest', function () {
    [, $store] = actingSeller();
    $invalid = Item::factory()->for($store)->create(['status' => Item::STATUS[Item::STATUS_DRAFT]]);
    $valid = Item::factory()->for($store)->create(['status' => Item::STATUS[Item::STATUS_DRAFT]]);
    $valid->is_valid = 1;
    $valid->save();

    $this->putJson('/api/seller/product/bulk/move', [
        'products' => [(string) $invalid->uuid, (string) $valid->uuid],
        'status' => 'ACTIVE',
    ])->assertUnprocessable()
        ->assertJsonPath('products.0.uuid', (string) $invalid->uuid);

    expect($valid->refresh()->status)->toBe(Item::STATUS[Item::STATUS_ACTIVE]);

    $this->putJson('/api/seller/product/bulk/move', [
        'products' => [(string) $valid->uuid],
        'status' => 'DRAFT',
    ])->assertNoContent();

    expect($valid->refresh()->status)->toBe(Item::STATUS[Item::STATUS_DRAFT])
        ->and($valid->manually_deactivated)->toBeTrue();
});

test('seller category list includes the full path', function () {
    actingSeller();
    $parent = Category::query()->create([
        'name' => 'Dog supplies',
        'slug' => 'dog-supplies',
        'is_active' => true,
    ]);
    Category::query()->create([
        'name' => 'Toys',
        'slug' => 'dog-toys',
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);

    $this->getJson('/api/seller/product/category')
        ->assertOk()
        ->assertJsonFragment(['full_path' => 'Dog supplies > Toys']);
});

test('category facets include the parent facet', function () {
    actingSeller();
    $parent = Category::query()->create(['name' => 'Dog supplies', 'slug' => 'dog-supplies', 'is_active' => true]);
    $child = Category::query()->create([
        'name' => 'Toys',
        'slug' => 'dog-toys',
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);
    $facet = CategoryFacet::query()->create([
        'title' => 'Material',
        'category_id' => $parent->id,
        'display_order' => 1,
        'is_active' => true,
    ]);
    FacetValue::query()->create([
        'name' => 'Rubber',
        'category_facet_id' => $facet->id,
        'display_order' => 1,
        'is_active' => true,
    ]);

    $this->getJson('/api/seller/product/category/'.$child->uuid.'/facets')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Material')
        ->assertJsonPath('data.0.values.0.name', 'Rubber');
});

test('a non english category list uses the translation table', function () {
    actingSeller();
    Cache::flush();
    $parent = Category::query()->create([
        'name' => 'Dog supplies',
        'slug' => 'dog-supplies',
        'is_active' => true,
    ]);
    $child = Category::query()->create([
        'name' => 'Toys',
        'slug' => 'dog-toys',
        'parent_id' => $parent->id,
        'is_active' => true,
    ]);
    CategoryTranslation::query()->create([
        'categories_id' => $parent->id,
        'lang_type' => 'es',
        'type' => 'category',
        'title' => 'Suministros',
    ]);
    CategoryTranslation::query()->create([
        'categories_id' => $child->id,
        'lang_type' => 'es',
        'type' => 'category',
        'title' => 'Juguetes',
    ]);

    $this->withHeader('X-localization', 'es')
        ->getJson('/api/seller/product/category')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Juguetes', 'full_path' => 'Suministros > Juguetes']);
});

test('category facets use a stored translation before google', function () {
    actingSeller();
    $category = Category::query()->create(['name' => 'Toys', 'slug' => 'dog-toys', 'is_active' => true]);
    $facet = CategoryFacet::query()->create([
        'title' => 'Material',
        'category_id' => $category->id,
        'display_order' => 1,
        'is_active' => true,
    ]);
    $value = FacetValue::query()->create([
        'name' => 'Rubber',
        'category_facet_id' => $facet->id,
        'display_order' => 1,
        'is_active' => true,
    ]);
    FacetTranslation::query()->create([
        'entity_id' => $facet->id,
        'entity_type' => FacetTranslation::ENTITY_TYPE_CATEGORY_FACET,
        'locale' => 'fr',
        'text' => 'Matiere',
    ]);
    FacetTranslation::query()->create([
        'entity_id' => $value->id,
        'entity_type' => FacetTranslation::ENTITY_TYPE_FACET_VALUE,
        'locale' => 'fr',
        'text' => 'Caoutchouc',
    ]);
    Http::fake();

    $this->withHeader('X-localization', 'fr')
        ->getJson('/api/seller/product/category/'.$category->uuid.'/facets')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Matiere')
        ->assertJsonPath('data.0.values.0.name', 'Caoutchouc');

    Http::assertNothingSent();
});

test('category facets ask google when a translation is missing', function () {
    actingSeller();
    $category = Category::query()->create(['name' => 'Toys', 'slug' => 'dog-toys-fr', 'is_active' => true]);
    $facet = CategoryFacet::query()->create([
        'title' => 'Material',
        'category_id' => $category->id,
        'display_order' => 1,
        'is_active' => true,
    ]);
    $value = FacetValue::query()->create([
        'name' => 'Rubber',
        'category_facet_id' => $facet->id,
        'display_order' => 1,
        'is_active' => true,
    ]);
    Config::set('services.google_translate.api_key', 'test-key');
    Http::fake([
        'translation.googleapis.com/*' => Http::response([
            'data' => [
                'translations' => [
                    ['translatedText' => 'Materiau'],
                    ['translatedText' => 'Caoutchouc'],
                ],
            ],
        ]),
    ]);

    $this->withHeader('X-localization', 'fr')
        ->getJson('/api/seller/product/category/'.$category->uuid.'/facets')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Materiau')
        ->assertJsonPath('data.0.values.0.name', 'Caoutchouc');

    expect(FacetTranslation::query()
        ->where('entity_id', $facet->id)
        ->where('entity_type', FacetTranslation::ENTITY_TYPE_CATEGORY_FACET)
        ->value('text'))->toBe('Materiau')
        ->and(FacetTranslation::query()
            ->where('entity_id', $value->id)
            ->where('entity_type', FacetTranslation::ENTITY_TYPE_FACET_VALUE)
            ->value('text'))->toBe('Caoutchouc');
});

test('ai description returns the model text', function () {
    actingSeller();

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'A sturdy chew toy.']],
            ],
        ]),
    ]);

    $this->postJson('/api/seller/product/generate-description', [
        'description' => 'Rubber toy',
        'product_name' => 'Chew Toy',
    ])->assertOk()
        ->assertJsonPath('description', 'A sturdy chew toy.');
});

test('category recognition returns the matched category uuid', function () {
    actingSeller();
    $category = makeCategory();

    Http::fake([
        'api.openai.com/*' => Http::response([
            'output' => [[
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode(['id' => $category->id, 'full_path' => 'Toys']),
                ]],
            ]],
        ]),
    ]);

    $this->postJson('/api/seller/product/recognize-category', [
        'product_name' => 'Chew Toy',
        'description' => 'A rubber toy',
        'product_type' => 'PHYSICAL',
    ])->assertOk()
        ->assertJsonPath('data.uuid', (string) $category->uuid)
        ->assertJsonPath('message', 'Category recognized successfully');
});

test('csv export queues a batch', function () {
    [, $store] = actingSeller();
    $item = Item::factory()->for($store)->create();
    Bus::fake();

    $this->postJson('/api/seller/product/export-csv', [
        'products' => [(string) $item->uuid],
        'status' => 'DRAFT',
    ])->assertOk()
        ->assertJsonPath('message', 'Product export CSV file is being prepared. You will receive an email when it is ready.');

    expect(ExportProduct::query()->where('product_uuid', (string) $item->uuid)->exists())->toBeTrue();
    Bus::assertDispatched(ExportProductsJob::class);
});

test('csv export writes the file and emails the link', function () {
    [$seller, $store] = actingSeller();
    $item = Item::factory()->for($store)->create([
        'title' => 'Chew Toy',
        'status' => Item::STATUS[Item::STATUS_DRAFT],
        'origin' => 'US',
    ]);
    ItemPrice::query()->create([
        'item_id' => $item->id,
        'domestic_price' => 1250,
        'is_global_pricing' => false,
        'stock_quantity' => 5,
        'weight' => 150,
        'sku' => 'CHEW-1',
        'allow_overselling' => false,
    ]);
    $batchId = (string) str()->uuid();
    ExportProduct::query()->insert([
        'batch_id' => $batchId,
        'product_uuid' => (string) $item->uuid,
    ]);

    Storage::fake('mps3');
    Mail::fake();
    Config::set('services.marketplace_media', 'https://cdn.test/');

    (new ExportProductsJob([
        'batch_id' => $batchId,
        'status' => 'DRAFT',
        'store_id' => $store->id,
    ]))->handle();

    $path = Storage::disk('mps3')->allFiles()[0];
    $csv = Storage::disk('mps3')->get($path);

    expect($csv)->toContain('product_title')
        ->and($csv)->toContain('Chew Toy')
        ->and($csv)->toContain('12.5')
        ->and($csv)->toContain('CHEW-1');

    Mail::assertQueued(ProductsExportEmail::class, function (ProductsExportEmail $mail) use ($seller, $store) {
        return $mail->hasTo($seller->email)
            && $mail->storeName === $store->name
            && str_starts_with($mail->url, 'https://cdn.test/exports/csv/');
    });
});

test('creating a product stores variants and uses the lowest price', function () {
    [, $store] = actingSeller();
    $category = makeCategory();

    $this->postJson('/api/seller/product', activeProductPayload($category, [
        'item_variations' => [
            'variants' => [
                ['name' => 'Size', 'options' => ['Small', 'Medium']],
            ],
            'combination' => [
                [
                    'variants' => [['type' => 'Size', 'value' => 'Small']],
                    'domestic_price' => 8,
                    'quantity' => 3,
                    'sku' => 'CHEW-S',
                    'is_visible' => true,
                ],
                [
                    'variants' => [['type' => 'Size', 'value' => 'Medium']],
                    'domestic_price' => 10,
                    'quantity' => 1,
                    'sku' => 'CHEW-M',
                    'is_visible' => true,
                ],
            ],
            'vary_by' => [
                'sku' => true,
                'quantity' => true,
                'price' => true,
                'weight' => false,
            ],
        ],
    ]))->assertCreated();

    $item = Item::query()->where('store_id', $store->id)->first();
    $small = $item->variants()->where('sku', 'CHEW-S')->first();

    expect($item->price->domestic_price)->toEqual(800)
        ->and($item->variants()->count())->toBe(2)
        ->and($small->stock_quantity)->toBe(3)
        ->and($small->attributeOptions()->pluck('attribute_value')->all())->toBe(['Small'])
        ->and($item->varyBy->price)->toBe(1);

    $this->putJson('/api/seller/product/'.$item->uuid, activeProductPayload($category, [
        'about' => ['is_description_edited' => false],
        'item_variations' => [
            'variants' => [
                ['name' => 'Size', 'options' => ['Small']],
            ],
            'combination' => [
                [
                    'uuid' => (string) $small->uuid,
                    'variants' => [['type' => 'Size', 'value' => 'Small']],
                    'domestic_price' => 9,
                    'quantity' => 4,
                    'sku' => 'CHEW-S',
                    'is_visible' => true,
                ],
            ],
            'vary_by' => [
                'sku' => true,
                'quantity' => false,
                'price' => true,
                'weight' => false,
            ],
        ],
    ]))->assertOk();

    $item->refresh();

    expect($item->variants()->pluck('sku')->all())->toBe(['CHEW-S'])
        ->and($item->variants()->first()->domestic_price)->toEqual(900)
        ->and($item->price->domestic_price)->toEqual(900)
        ->and($item->attributes()->first()->attributeOptions()->pluck('attribute_value')->all())->toBe(['Small']);
});

test('updating a product stores a discount and customization', function () {
    [, $store] = actingSeller();
    $category = makeCategory();

    $this->postJson('/api/seller/product', activeProductPayload($category, [
        'about' => [
            'customizations' => [
                'enabled' => true,
                'description' => 'Engrave a name',
                'description_enabled' => true,
                'limit' => 20,
                'attach_file' => true,
            ],
        ],
    ]))->assertCreated();

    $item = Item::query()->where('store_id', $store->id)->first();

    expect($item->customization->attach_file)->toBe(1)
        ->and($item->customization->description)->toBe('Engrave a name')
        ->and($item->customization->limit)->toBe(20)
        ->and($item->discount)->toBeNull();

    $this->putJson('/api/seller/product/'.$item->uuid, activeProductPayload($category, [
        'about' => [
            'is_description_edited' => false,
            'customizations' => [
                'enabled' => true,
                'description_enabled' => true,
                'description' => 'Engrave a name',
                'attach_file' => false,
            ],
        ],
        'discount' => [
            'value' => 15,
            'is_limited' => true,
        ],
    ]))->assertOk();

    $item->refresh();

    expect($item->customization->description)->toBe('Engrave a name')
        ->and($item->customization->limit)->toBeNull()
        ->and($item->discount->value)->toBe(1500)
        ->and($item->discount->start_date)->toBeNull();

    $this->putJson('/api/seller/product/'.$item->uuid, activeProductPayload($category, [
        'about' => ['is_description_edited' => false],
    ]))->assertOk();

    $item->unsetRelation('customization')->unsetRelation('discount');

    expect($item->customization)->toBeNull()
        ->and($item->discount)->toBeNull();
});

test('creating a product stores the price and variant gtin', function () {
    [, $store] = actingSeller();
    $category = makeCategory();

    $this->postJson('/api/seller/product', activeProductPayload($category, [
        'price_inventory' => ['gtin' => '012345678905'],
        'item_variations' => [
            'variants' => [
                ['name' => 'Size', 'options' => ['Small']],
            ],
            'combination' => [
                [
                    'variants' => [['type' => 'Size', 'value' => 'Small']],
                    'domestic_price' => 8,
                    'sku' => 'CHEW-S',
                    'gtin' => '012345678912',
                    'is_visible' => true,
                ],
            ],
            'vary_by' => [
                'sku' => true,
                'quantity' => false,
                'price' => true,
                'weight' => false,
            ],
        ],
    ]))->assertCreated();

    $item = Item::query()->where('store_id', $store->id)->first();

    expect($item->price->gtin)->toBe('012345678905')
        ->and($item->variants()->first()->gtin)->toBe('012345678912');

    $this->putJson('/api/seller/product/'.$item->uuid, activeProductPayload($category, [
        'about' => ['is_description_edited' => false],
        'price_inventory' => ['gtin' => '099999999999'],
    ]))->assertOk();

    expect($item->price()->first()->gtin)->toBe('099999999999');
});

test('creating a product attaches gallery images in order', function () {
    [, $store] = actingSeller();
    $category = makeCategory();
    $cover = new Gallery;
    $cover->store_id = $store->id;
    $cover->url = 'uploads/products/cover.webp';
    $cover->type = Gallery::TYPE_IMAGE;
    $cover->save();
    $extra = new Gallery;
    $extra->store_id = $store->id;
    $extra->url = 'uploads/products/side.webp';
    $extra->type = Gallery::TYPE_IMAGE;
    $extra->save();

    $this->postJson('/api/seller/product', activeProductPayload($category, [
        'about' => [
            'images' => [(string) $cover->uuid, (string) $extra->uuid],
        ],
    ]))->assertCreated();

    $item = Item::query()->where('store_id', $store->id)->first();

    expect($item->media_url)->toBe('uploads/products/cover.webp')
        ->and(ItemMedia::query()->where('item_id', $item->id)->orderBy('order')->pluck('gallery_id')->all())
        ->toBe([$cover->id, $extra->id]);

    $this->putJson('/api/seller/product/'.$item->uuid, activeProductPayload($category, [
        'about' => [
            'is_description_edited' => false,
            'images' => [(string) $extra->uuid],
        ],
    ]))->assertOk();

    $item->refresh();

    expect($item->media_url)->toBe('uploads/products/side.webp')
        ->and(ItemMedia::query()->where('item_id', $item->id)->pluck('gallery_id')->all())
        ->toBe([$extra->id]);
});

test('product show and list return the saved product details', function () {
    [, $store] = actingSeller();
    $category = makeCategory();
    $section = new Section;
    $section->store_id = $store->id;
    $section->name = 'Toys';
    $section->slug = 'toys';
    $section->is_visible = 1;
    $section->order = 1;
    $section->save();
    $image = new Gallery;
    $image->store_id = $store->id;
    $image->url = 'uploads/products/cover.webp';
    $image->name = 'Cover';
    $image->type = Gallery::TYPE_IMAGE;
    $image->save();

    $this->postJson('/api/seller/product', activeProductPayload($category, [
        'about' => ['images' => [(string) $image->uuid]],
        'category_tags' => [
            'category' => $category->uuid,
            'tags' => ['Chew'],
        ],
        'section' => [(string) $section->uuid],
        'item_variations' => [
            'variants' => [
                ['name' => 'Size', 'options' => ['Small']],
            ],
            'combination' => [
                [
                    'variants' => [['type' => 'Size', 'value' => 'Small']],
                    'domestic_price' => 8,
                    'sku' => 'CHEW-S',
                    'gtin' => '012345678912',
                    'is_visible' => true,
                ],
            ],
            'vary_by' => [
                'sku' => true,
                'quantity' => false,
                'price' => true,
                'weight' => false,
            ],
        ],
    ]))->assertCreated();

    $item = Item::query()->where('store_id', $store->id)->first();

    $this->putJson('/api/seller/product/'.$item->uuid, activeProductPayload($category, [
        'about' => ['is_description_edited' => false],
        'category_tags' => [
            'category' => $category->uuid,
            'tags' => ['Chew'],
        ],
        'section' => [(string) $section->uuid],
        'price_inventory' => ['gtin' => '012345678905'],
        'discount' => ['value' => 15, 'is_limited' => true],
    ]))->assertOk();

    $show = $this->getJson('/api/seller/product/'.$item->uuid)->assertOk();

    $show->assertJsonPath('data.prices.gtin', '012345678905')
        ->assertJsonPath('data.tags.0.name', 'Chew')
        ->assertJsonPath('data.sections.1.slug', 'toys')
        ->assertJsonPath('data.medias.0.url', 'uploads/products/cover.webp')
        ->assertJsonPath('data.attributes.0.name', 'Size')
        ->assertJsonPath('data.itemVariants.0.sku', 'CHEW-S')
        ->assertJsonPath('data.itemVariants.0.f_id', 'Small')
        ->assertJsonPath('data.itemVariants.0.domestic_price', 8)
        ->assertJsonPath('data.vary_by.price', 1)
        ->assertJsonPath('data.discount.value', 15);

    $this->getJson('/api/seller/product')
        ->assertOk()
        ->assertJsonPath('data.0.uuid', (string) $item->uuid)
        ->assertJsonPath('data.0.medias.0.name', 'Cover')
        ->assertJsonPath('data.0.discount.value', 15);
});

test('product show and list return active item facets', function () {
    [, $store] = actingSeller();
    $item = Item::factory()->for($store)->create();
    $facet = CategoryFacet::query()->create([
        'title' => 'Material',
        'display_order' => 1,
        'is_active' => true,
    ]);
    $active = FacetValue::query()->create([
        'name' => 'Rubber',
        'category_facet_id' => $facet->id,
        'display_order' => 1,
        'is_active' => true,
    ]);
    $inactive = FacetValue::query()->create([
        'name' => 'Plastic',
        'category_facet_id' => $facet->id,
        'display_order' => 2,
        'is_active' => false,
    ]);
    $item->activeFacets()->attach([$active->id, $inactive->id]);

    $this->getJson('/api/seller/product/'.$item->uuid)
        ->assertOk()
        ->assertJsonPath('data.facets.0.name', 'Rubber')
        ->assertJsonCount(1, 'data.facets');

    $this->getJson('/api/seller/product')
        ->assertOk()
        ->assertJsonPath('data.0.facets.0.id', $active->id);
});
