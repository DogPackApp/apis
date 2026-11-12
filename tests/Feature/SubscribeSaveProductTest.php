<?php

use App\Models\Product\Category;
use App\Models\Product\Item;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use App\Models\Store\StoreSetting;
use App\Models\SubscribeSave\ItemSubscribeSaveSetting;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
    Cache::flush();
});

function subscribeSaveSeller(): Store
{
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    return $store;
}

function subscribeSaveCategory(bool $enabled): Category
{
    return Category::query()->create([
        'name' => $enabled ? 'Food' : 'Toys',
        'slug' => $enabled ? 'food' : 'toys-'.fake()->unique()->numerify('###'),
        'ht_code' => '9503',
        'order' => '1',
        'is_active' => true,
        'subscribe_save_enabled' => $enabled,
    ]);
}

test('a product reports subscribe and save from its category and store discount', function () {
    $store = subscribeSaveSeller();
    StoreSetting::query()->create([
        'store_id' => $store->id,
        'subscribe_save_discount_percent' => 10,
        'dogpack_subscribe_save_first_order_percent' => 5,
    ]);
    $category = subscribeSaveCategory(true);
    $item = Item::factory()->for($store)->create([
        'category_id' => $category->id,
        'type' => Item::TYPES[Item::TYPE_PHYSICAL],
    ]);

    $this->getJson("/api/seller/product/{$item->uuid}")
        ->assertOk()
        ->assertJsonPath('data.subscribe_save.label', true)
        ->assertJsonPath('data.subscribe_save.eligible', true)
        ->assertJsonPath('data.subscribe_save.effective_discount_percent', 10)
        ->assertJsonPath('data.subscribe_save.dogpack_first_order_percent', 5)
        ->assertJsonPath('data.subscribe_save.reason', null);

    $digital = Item::factory()->for($store)->create([
        'category_id' => $category->id,
        'type' => Item::TYPES[Item::TYPE_DIGITAL],
    ]);

    $this->getJson("/api/seller/product/{$digital->uuid}")
        ->assertOk()
        ->assertJsonPath('data.subscribe_save.label', false)
        ->assertJsonPath('data.subscribe_save.reason', 'digital');
});

test('a seller can save a product subscribe and save discount', function () {
    $store = subscribeSaveSeller();
    StoreSetting::query()->create([
        'store_id' => $store->id,
        'subscribe_save_discount_percent' => 10,
    ]);
    $item = Item::factory()->for($store)->create([
        'category_id' => subscribeSaveCategory(true)->id,
    ]);

    $this->putJson("/api/seller/product/{$item->uuid}", [
        'subscribe_save' => [
            'seller_disabled' => true,
            'discount_percent' => 15,
        ],
    ])->assertOk();

    $this->getJson("/api/seller/product/{$item->uuid}")
        ->assertOk()
        ->assertJsonPath('data.subscribe_save.seller_disabled', true)
        ->assertJsonPath('data.subscribe_save.discount_percent', 15)
        ->assertJsonPath('data.subscribe_save.eligible', false)
        ->assertJsonPath('data.subscribe_save.reason', 'seller_disabled');
});

test('a product discount is rejected until the store has a global discount', function () {
    $store = subscribeSaveSeller();
    $item = Item::factory()->for($store)->create();

    $response = $this->putJson("/api/seller/product/{$item->uuid}", [
        'subscribe_save' => ['discount_percent' => 15],
    ])->assertUnprocessable();

    expect($response->json('errors')['subscribe_save.discount_percent'][0])
        ->toBe('Set the store global discount before a product discount.');
});

test('the subscribe and save product list keeps seller-disabled products and drops digital and force off', function () {
    $store = subscribeSaveSeller();
    StoreSetting::query()->create([
        'store_id' => $store->id,
        'subscribe_save_discount_percent' => 10,
    ]);
    $enabled = subscribeSaveCategory(true);
    $disabled = subscribeSaveCategory(false);

    Item::factory()->for($store)->create(['title' => 'In program', 'category_id' => $enabled->id]);
    $sellerOff = Item::factory()->for($store)->create(['title' => 'Seller off', 'category_id' => $enabled->id]);
    ItemSubscribeSaveSetting::query()->create([
        'item_id' => $sellerOff->id,
        'seller_disabled' => true,
    ]);
    Item::factory()->for($store)->create([
        'title' => 'Digital',
        'category_id' => $enabled->id,
        'type' => Item::TYPES[Item::TYPE_DIGITAL],
    ]);
    $forcedOff = Item::factory()->for($store)->create(['title' => 'Forced off', 'category_id' => $enabled->id]);
    ItemSubscribeSaveSetting::query()->create([
        'item_id' => $forcedOff->id,
        'admin_override' => ItemSubscribeSaveSetting::ADMIN_OVERRIDE_FORCE_OFF,
    ]);
    $forcedOn = Item::factory()->for($store)->create(['title' => 'Forced on', 'category_id' => $disabled->id]);
    ItemSubscribeSaveSetting::query()->create([
        'item_id' => $forcedOn->id,
        'admin_override' => ItemSubscribeSaveSetting::ADMIN_OVERRIDE_FORCE_ON,
    ]);
    Item::factory()->for($store)->create(['title' => 'Category off', 'category_id' => $disabled->id]);

    $titles = collect($this->getJson('/api/seller/product?subscribe_save=1')->assertOk()->json('data'))->pluck('title');

    expect($titles->all())->toEqualCanonicalizing(['In program', 'Seller off', 'Forced on']);
});
