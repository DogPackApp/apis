<?php

use App\Mail\SellerWelcomeEmail;
use App\Models\Seller\OnboardingStatus;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
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

test('seller can create a store, completes onboarding step and receives a welcome email', function () {
    Mail::fake();

    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/store', [
        'name' => 'Ada\'s Widgets',
        'description' => 'The best widgets.',
        'timezone' => 'America/Toronto',
    ])->assertCreated()
        ->assertJsonPath('data.name', "Ada's Widgets")
        ->assertJsonPath('data.slug', 'adas-widgets')
        ->assertJsonPath('data.timezone', 'America/Toronto');

    expect(Store::query()->where('seller_id', $seller->id)->exists())->toBeTrue();

    $store = Store::query()->where('seller_id', $seller->id)->first();

    $onboarding = OnboardingStatus::query()->where('seller_id', $seller->id)->first();
    expect($onboarding)->not->toBeNull()
        ->and($onboarding->is_store_setting)->toBe(1)
        ->and($onboarding->store_id)->toBe($store->id);

    Mail::assertQueued(SellerWelcomeEmail::class);
});

test('seller can upload a store image and cover image on create', function () {
    Storage::fake('public');

    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $response = $this->post('/api/seller/store', [
        'name' => 'Image Store',
        'image' => UploadedFile::fake()->image('logo.jpg'),
        'cover_image' => UploadedFile::fake()->image('cover.jpg'),
    ])->assertCreated();

    $store = Store::query()->where('seller_id', $seller->id)->first();

    expect($store->getRawOriginal('image'))->not->toBeNull()
        ->and($store->getRawOriginal('cover_image'))->not->toBeNull();

    Storage::disk('public')->assertExists($store->getRawOriginal('image'));
    Storage::disk('public')->assertExists($store->getRawOriginal('cover_image'));

    expect($response->json('data.image'))->toContain('/storage/');
});

test('store update rejects a non-image file for image', function () {
    $seller = Seller::factory()->verified()->create();
    Store::factory()->create(['seller_id' => $seller->id]);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->post('/api/seller/store', [
        '_method' => 'PUT',
        'image' => UploadedFile::fake()->create('not-an-image.pdf', 10),
    ])->assertUnprocessable();
});

test('seller can upload a new store image on update via method-spoofed post', function () {
    Storage::fake('public');

    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->post('/api/seller/store', [
        '_method' => 'PUT',
        'image' => UploadedFile::fake()->image('new-logo.jpg'),
    ])->assertOk();

    $store->refresh();

    expect($store->getRawOriginal('image'))->not->toBeNull();
    Storage::disk('public')->assertExists($store->getRawOriginal('image'));
});

test('seller cannot create a second store', function () {
    $seller = Seller::factory()->verified()->create();
    Store::factory()->create(['seller_id' => $seller->id]);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/store', ['name' => 'Second Store'])
        ->assertUnprocessable();
});

test('seller can update their store', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id, 'timezone' => 'UTC']);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->putJson('/api/seller/store', ['timezone' => 'America/New_York'])
        ->assertOk()
        ->assertJsonPath('data.uuid', $store->uuid)
        ->assertJsonPath('data.timezone', 'America/New_York');
});

test('store update rejects an invalid timezone', function () {
    $seller = Seller::factory()->verified()->create();
    Store::factory()->create(['seller_id' => $seller->id]);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->putJson('/api/seller/store', ['timezone' => 'Not/A/Real/Zone'])
        ->assertUnprocessable();
});

test('store show returns not found when seller has no store', function () {
    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/seller/store')->assertNotFound();
});
