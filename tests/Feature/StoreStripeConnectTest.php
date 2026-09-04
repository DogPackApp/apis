<?php

use App\Models\Misc\Country;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use App\Services\StripeConnectService;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('stripe connect requires authentication', function () {
    $this->postJson('/api/seller/stripe/connect')->assertUnauthorized();
});

test('stripe connect returns not found when seller has no store', function () {
    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/stripe/connect')->assertNotFound();
});

test('stripe connect creates a new express account and stores the id', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id, 'country_id' => null]);

    $this->mock(StripeConnectService::class, function ($mock) {
        $mock->shouldReceive('createExpressAccount')
            ->once()
            ->with('US', 'full', 'sk_test_default')
            ->andReturn('acct_new123');

        $mock->shouldReceive('createAccountLink')
            ->once()
            ->with('acct_new123', 'sk_test_default')
            ->andReturn('https://connect.stripe.com/setup/new123');
    });

    config(['services.marketplace_stripe.key' => 'sk_test_default']);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/stripe/connect')
        ->assertOk()
        ->assertJsonPath('url', 'https://connect.stripe.com/setup/new123');

    $store->refresh();
    expect($store->stripe_account_id)->toBe('acct_new123')
        ->and($store->stripe_status)->toBe(Store::STRIPE_STATUS_PENDING);
});

test('stripe connect uses the uk config and full agreement for a GB store', function () {
    Country::query()->create([
        'id' => 230,
        'short_name' => 'GB',
        'name' => 'United Kingdom',
    ]);

    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id, 'country_id' => 230]);

    $this->mock(StripeConnectService::class, function ($mock) {
        $mock->shouldReceive('createExpressAccount')
            ->once()
            ->with('GB', 'full', 'sk_test_uk')
            ->andReturn('acct_uk123');

        $mock->shouldReceive('createAccountLink')
            ->once()
            ->with('acct_uk123', 'sk_test_uk')
            ->andReturn('https://connect.stripe.com/setup/uk123');
    });

    config(['services.marketplace_stripe_uk.key' => 'sk_test_uk']);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/stripe/connect')
        ->assertOk()
        ->assertJsonPath('url', 'https://connect.stripe.com/setup/uk123');
});

test('stripe connect refreshes the link for an existing pending account without creating a new one', function () {
    $seller = Seller::factory()->verified()->create();
    Store::factory()->create([
        'seller_id' => $seller->id,
        'stripe_account_id' => 'acct_existing',
        'stripe_status' => Store::STRIPE_STATUS_PENDING,
    ]);

    $this->mock(StripeConnectService::class, function ($mock) {
        $mock->shouldReceive('createExpressAccount')->never();

        $mock->shouldReceive('createAccountLink')
            ->once()
            ->with('acct_existing', 'sk_test_default')
            ->andReturn('https://connect.stripe.com/setup/refresh');
    });

    config(['services.marketplace_stripe.key' => 'sk_test_default']);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/stripe/connect')
        ->assertOk()
        ->assertJsonPath('url', 'https://connect.stripe.com/setup/refresh');
});

test('stripe connect returns 422 when the stripe request fails', function () {
    $seller = Seller::factory()->verified()->create();
    Store::factory()->create(['seller_id' => $seller->id]);

    $this->mock(StripeConnectService::class, function ($mock) {
        $mock->shouldReceive('createExpressAccount')
            ->andThrow(new RuntimeException('Stripe is down'));
    });

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/stripe/connect')
        ->assertUnprocessable()
        ->assertJsonPath('errors.message', 'Stripe is down');
});
