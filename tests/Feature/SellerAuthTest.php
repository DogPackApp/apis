<?php

use App\Mail\SellerOTPMail;
use App\Models\Seller\OnboardingStatus;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use App\Services\OTPService;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('seller registration requires fields', function () {
    $this->postJson('/api/seller/register')
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['first_name', 'last_name', 'email', 'password']]);
});

test('seller can register and receives a token and otp email', function () {
    Mail::fake();

    $response = $this->postJson('/api/seller/register', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'password' => 'secret1',
        'password_confirmation' => 'secret1',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'ada@example.com')
        ->assertJsonPath('data.status', 0)
        ->assertJsonStructure(['data' => ['uuid', 'first_name', 'last_name', 'email'], 'token']);

    Mail::assertQueued(SellerOTPMail::class);
    expect(Seller::query()->where('email', 'ada@example.com')->exists())->toBeTrue();
});

test('seller login rejects invalid credentials', function () {
    Seller::factory()->create([
        'email' => 'seller@example.com',
    ]);

    $this->postJson('/api/seller/login', [
        'email' => 'seller@example.com',
        'password' => 'wrong-password',
    ])->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid credentials');
});

test('seller login returns a token when 2fa is disabled', function () {
    $seller = Seller::factory()->verified()->create([
        'email' => 'seller@example.com',
    ]);

    $this->postJson('/api/seller/login', [
        'email' => $seller->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('data.email', $seller->email)
        ->assertJsonStructure(['token']);
});

test('seller login rejects a seller whose store is inactive', function () {
    $seller = Seller::factory()->verified()->create([
        'email' => 'blocked-seller@example.com',
    ]);
    Store::factory()->create([
        'seller_id' => $seller->id,
        'states' => Store::STATES_INACTIVE,
    ]);

    $this->postJson('/api/seller/login', [
        'email' => $seller->email,
        'password' => 'password',
    ])->assertUnauthorized()
        ->assertJsonPath('message', fn ($message) => str_contains($message, 'Your account is blocked'));
});

test('seller login allows a seller whose store is active', function () {
    $seller = Seller::factory()->verified()->create([
        'email' => 'active-store-seller@example.com',
    ]);
    Store::factory()->create([
        'seller_id' => $seller->id,
        'states' => Store::STATES_ACTIVE,
    ]);

    $this->postJson('/api/seller/login', [
        'email' => $seller->email,
        'password' => 'password',
    ])->assertOk();
});

test('seller login sends otp when 2fa is enabled', function () {
    Mail::fake();

    $seller = Seller::factory()->verified()->withTwoFactor()->create([
        'email' => 'seller@example.com',
    ]);

    $this->postJson('/api/seller/login', [
        'email' => $seller->email,
        'password' => 'password',
    ])->assertAccepted()
        ->assertJsonPath('message', 'OTP sent to your email.');

    Mail::assertQueued(SellerOTPMail::class);
});

test('seller login otp rejects unknown email', function () {
    $this->postJson('/api/seller/login/otp', [
        'email' => 'missing@example.com',
        'otp' => 1234,
    ])->assertBadRequest()
        ->assertJsonPath('message', 'Invalid or expired OTP.');
});

test('seller can complete 2fa login with otp', function () {
    Mail::fake();

    $seller = Seller::factory()->verified()->withTwoFactor()->create([
        'email' => 'seller@example.com',
    ]);

    $this->postJson('/api/seller/login', [
        'email' => $seller->email,
        'password' => 'password',
    ])->assertAccepted();

    $this->postJson('/api/seller/login/otp', [
        'email' => $seller->email,
        'otp' => 1234,
    ])->assertOk()
        ->assertJsonPath('data.email', $seller->email)
        ->assertJsonStructure(['token']);
});

test('seller profile requires authentication', function () {
    $this->getJson('/api/seller/me')->assertUnauthorized();
});

test('authenticated seller can view their profile', function () {
    $seller = Seller::factory()->verified()->create();

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/seller/me')
        ->assertOk()
        ->assertJsonPath('data.uuid', $seller->uuid)
        ->assertJsonPath('data.email', $seller->email)
        ->assertJsonPath('data.store', null);
});

test('authenticated seller profile includes their store once created', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/seller/me')
        ->assertOk()
        ->assertJsonPath('data.store.uuid', $store->uuid)
        ->assertJsonPath('data.store.name', $store->name);
});

test('authenticated seller can logout', function () {
    $seller = Seller::factory()->verified()->create();
    $token = $seller->createToken('SellerAuthToken', ['seller'])->accessToken;

    $this->withToken($token)
        ->postJson('/api/seller/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logged out successfully');
});

test('seller verify rejects an invalid otp', function () {
    $seller = Seller::factory()->create();

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/verify', ['otp' => 9999])
        ->assertBadRequest()
        ->assertJsonPath('message', 'Invalid or expired OTP.');
});

test('seller can verify otp and start onboarding', function () {
    $seller = Seller::factory()->create(['status' => 0]);
    (new OTPService($seller))->invalidate()->generate();

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/verify', ['otp' => 1234])
        ->assertOk()
        ->assertJsonPath('message', 'OTP verified successfully.');

    expect($seller->fresh()->status)->toBe(1)
        ->and(OnboardingStatus::query()->where('seller_id', $seller->id)->exists())->toBeTrue();
});

test('onboarding status returns no content when missing', function () {
    $seller = Seller::factory()->verified()->create();

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/seller/onboarding/status')->assertNoContent();
});

test('onboarding status returns the seller onboarding resource', function () {
    $seller = Seller::factory()->verified()->create();
    OnboardingStatus::query()->create([
        'seller_id' => $seller->id,
        'is_product' => 0,
        'is_shipping' => 0,
        'is_store_setting' => 0,
        'is_finance' => 0,
        'is_subscribe' => 0,
    ]);

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/seller/onboarding/status')
        ->assertOk()
        ->assertJsonStructure(['data' => ['uuid', 'is_product', 'is_shipping', 'is_store_setting', 'is_finance', 'is_subscribe']]);
});

test('seller cannot update another seller', function () {
    $seller = Seller::factory()->verified()->create();
    $other = Seller::factory()->verified()->create();

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->putJson('/api/seller/'.$other->uuid, [
        'first_name' => 'Hacked',
        'last_name' => 'Name',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.others', 'Action not allowed.');
});

test('seller can update their own profile', function () {
    $seller = Seller::factory()->verified()->create();

    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->putJson('/api/seller/'.$seller->uuid, [
        'first_name' => 'Updated',
        'last_name' => 'Seller',
        'phone' => '5551234567',
    ])->assertOk()
        ->assertJsonPath('data.first_name', 'Updated')
        ->assertJsonPath('data.last_name', 'Seller')
        ->assertJsonPath('data.phone', '5551234567');
});
