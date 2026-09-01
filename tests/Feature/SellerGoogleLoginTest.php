<?php

use App\Mail\SellerOTPMail;
use App\Models\Seller\OnboardingStatus;
use App\Models\Seller\Seller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('google login requires a code', function () {
    $this->postJson('/api/seller/google/login')
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['code']]);
});

test('google login creates a seller and starts onboarding', function () {
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
        'https://www.googleapis.com/oauth2/v2/userinfo' => Http::response([
            'id' => 'google-1',
            'email' => 'google-seller@example.com',
            'verified_email' => true,
            'given_name' => 'Google',
            'family_name' => 'Seller',
        ]),
    ]);

    $this->postJson('/api/seller/google/login', [
        'code' => 'auth-code',
    ])->assertOk()
        ->assertJsonPath('data.email', 'google-seller@example.com')
        ->assertJsonPath('data.status', 1)
        ->assertJsonStructure(['token']);

    $seller = Seller::query()->where('email', 'google-seller@example.com')->first();

    expect($seller)->not->toBeNull()
        ->and($seller->google_id)->toBe('google-1')
        ->and(OnboardingStatus::query()->where('seller_id', $seller->id)->exists())->toBeTrue();
});

test('google login links google id and sends otp when 2fa is enabled', function () {
    Mail::fake();

    $seller = Seller::factory()->verified()->withTwoFactor()->create([
        'email' => 'google-seller@example.com',
        'google_id' => null,
    ]);

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
        'https://www.googleapis.com/oauth2/v2/userinfo' => Http::response([
            'id' => 'google-99',
            'email' => $seller->email,
            'verified_email' => true,
            'given_name' => 'Google',
            'family_name' => 'Seller',
        ]),
    ]);

    $this->postJson('/api/seller/google/login', [
        'code' => 'auth-code',
    ])->assertAccepted()
        ->assertJsonPath('twoFa', true)
        ->assertJsonPath('email', $seller->email);

    Mail::assertQueued(SellerOTPMail::class);
    expect($seller->fresh()->google_id)->toBe('google-99');
});

test('google login fails when google email is not verified', function () {
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'google-access-token']),
        'https://www.googleapis.com/oauth2/v2/userinfo' => Http::response([
            'id' => 'google-1',
            'email' => 'google-seller@example.com',
            'verified_email' => false,
        ]),
    ]);

    $this->postJson('/api/seller/google/login', [
        'code' => 'auth-code',
    ])->assertBadRequest()
        ->assertJsonPath('message', 'Email not verified with Google');
});
