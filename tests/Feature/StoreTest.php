<?php

use App\Mail\SellerWelcomeEmail;
use App\Models\Seller\OnboardingStatus;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use Illuminate\Support\Facades\Mail;
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

    $onboarding = OnboardingStatus::query()->where('seller_id', $seller->id)->first();
    expect($onboarding)->not->toBeNull()
        ->and($onboarding->is_store_setting)->toBe(1);

    Mail::assertQueued(SellerWelcomeEmail::class);
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
