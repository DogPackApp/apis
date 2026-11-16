<?php

use App\Models\Seller\Seller;
use App\Models\Store\Section;
use App\Models\Store\ShippingAddress;
use App\Models\Store\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('store creation requires authentication', function () {
    $this->postJson('/api/seller/store', ['name' => 'My Store'])
        ->assertUnauthorized();
});

test('seller can create a store', function () {
    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/store', [
        'name' => 'Ada\'s Widgets',
        'description' => 'The best widgets.',
        'phone' => '4165550100',
    ])->assertCreated()
        ->assertJsonPath('data.name', "Ada's Widgets")
        ->assertJsonPath('data.slug', 'adas-widgets')
        ->assertJsonPath('data.status', 0)
        ->assertJsonPath('data.states', 'PENDING')
        ->assertJsonPath('data.stripe_account_id', null)
        ->assertJsonPath('data.is_shopify_connected', false);

    $store = Store::query()->where('seller_id', $seller->id)->first();

    expect($store)->not->toBeNull()
        ->and($seller->fresh()->phone)->toBe('4165550100')
        ->and(Section::query()->where('store_id', $store->id)->where('slug', 'all')->exists())->toBeTrue();
});

test('seller can upload a store image on create', function () {
    Storage::fake('mps3');

    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $response = $this->post('/api/seller/store', [
        'name' => 'Image Store',
        'phone' => '4165550100',
        'image' => UploadedFile::fake()->image('logo.jpg'),
    ])->assertCreated();

    $store = Store::query()->where('seller_id', $seller->id)->first();

    expect($store->getRawOriginal('image'))->toStartWith('upload/store/');
    Storage::disk('mps3')->assertExists($store->getRawOriginal('image'));
    expect($response->json('data.image'))->toContain('upload/store/');
});

test('store update rejects a non-image file for image', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->post('/api/seller/store/'.$store->uuid, [
        'image' => UploadedFile::fake()->create('not-an-image.pdf', 10),
    ])->assertUnprocessable();
});

test('seller can upload a new store image on update', function () {
    Storage::fake('mps3');

    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->post('/api/seller/store/'.$store->uuid, [
        'image' => UploadedFile::fake()->image('new-logo.jpg'),
    ])->assertCreated();

    $store->refresh();

    expect($store->getRawOriginal('image'))->toStartWith('upload/store/');
    Storage::disk('mps3')->assertExists($store->getRawOriginal('image'));
});

test('seller cannot create a second store', function () {
    $seller = Seller::factory()->verified()->create();
    Store::factory()->create(['seller_id' => $seller->id]);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/store', [
        'name' => 'Second Store',
        'phone' => '4165550100',
    ])->assertUnprocessable()
        ->assertJsonPath('errors', 'Store Already Exists');
});

test('seller can update their store', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id, 'timezone' => 'UTC']);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/store/'.$store->uuid, ['timezone' => 'America/New_York'])
        ->assertCreated()
        ->assertJsonPath('data.uuid', $store->uuid)
        ->assertJsonPath('data.timezone', 'America/New_York')
        ->assertJsonPath('data.status', 1);
});

test('store update stores the timezone string it is given', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/store/'.$store->uuid, ['timezone' => 'Not/A/Real/Zone'])
        ->assertCreated()
        ->assertJsonPath('data.timezone', 'Not/A/Real/Zone');
});

test('store show returns not found when seller has no store', function () {
    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/seller/store')
        ->assertNotFound()
        ->assertJsonPath('message', 'No Store Found');
});

test('store address requires authentication', function () {
    $this->getJson('/api/seller/store/address')->assertUnauthorized();
});

test('store address fails when the seller has no store', function () {
    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/seller/store/address')->assertServerError();
});

test('store address returns an empty data object when the store has no address', function () {
    $seller = Seller::factory()->verified()->create();
    Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/seller/store/address')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('store address returns the saved shipping address', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    $address = ShippingAddress::query()->create([
        'store_id' => $store->id,
        'address' => '123 King St, Toronto, ON',
        'street1' => '123 King St',
        'street2' => null,
        'zipcode' => 'M5H 1A1',
        'city' => 'Toronto',
        'country' => 'Canada',
        'province' => 'ON',
        'latitude' => '43.65',
        'longitude' => '-79.38',
    ]);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/seller/store/address')
        ->assertOk()
        ->assertJsonPath('data.uuid', $address->uuid)
        ->assertJsonPath('data.address', '123 King St, Toronto, ON')
        ->assertJsonPath('data.street1', '123 King St')
        ->assertJsonPath('data.street2', null)
        ->assertJsonPath('data.zipcode', 'M5H 1A1')
        ->assertJsonPath('data.city', 'Toronto')
        ->assertJsonPath('data.country', 'Canada')
        ->assertJsonPath('data.latitude', 43.65)
        ->assertJsonPath('data.longitude', -79.38)
        ->assertJsonMissingPath('data.province')
        ->assertJsonMissingPath('data.easypost_id');
});

test('store address ignores a soft-deleted shipping address', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    $address = ShippingAddress::query()->create([
        'store_id' => $store->id,
        'address' => '123 King St, Toronto, ON',
        'street1' => '123 King St',
        'zipcode' => 'M5H 1A1',
        'city' => 'Toronto',
        'country' => 'Canada',
        'province' => 'ON',
    ]);
    $address->delete();

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/seller/store/address')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});
