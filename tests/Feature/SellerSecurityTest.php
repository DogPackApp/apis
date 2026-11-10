<?php

use App\Mail\SellerOTPMail;
use App\Mail\SellerPasswordUpdatedMail;
use App\Mail\SellerTwoFactorEnabledMail;
use App\Models\Seller\Seller;
use App\Services\OTPService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('current password requires authentication', function () {
    $this->postJson('/api/seller/password/current', ['password' => 'password'])
        ->assertUnauthorized();
});

test('current password rejects an incorrect password', function () {
    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/password/current', ['password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.password', 'The provided password is incorrect.');
});

test('current password returns a reset token when correct', function () {
    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/password/current', ['password' => 'password'])
        ->assertOk()
        ->assertJsonStructure(['token']);
});

test('seller can change their password and receives a confirmation email', function () {
    Mail::fake();

    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $token = Password::broker('sellers')->createToken($seller);

    $this->postJson('/api/seller/password/change', [
        'token' => $token,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertOk();

    Mail::assertQueued(SellerPasswordUpdatedMail::class);
});

test('change password rejects an invalid token', function () {
    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/password/change', [
        'token' => 'invalid-token',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertBadRequest();
});

test('two factor otp requires authentication', function () {
    $this->postJson('/api/seller/2fa/otp')->assertUnauthorized();
});

test('seller can request a 2fa enable otp', function () {
    Mail::fake();

    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/2fa/otp')
        ->assertOk()
        ->assertJsonPath('message', 'OTP sent to your email.');

    Mail::assertQueued(SellerOTPMail::class);
});

test('seller can enable 2fa with a valid otp and receives a confirmation email', function () {
    Mail::fake();

    $seller = Seller::factory()->verified()->create(['twofa_enabled' => 0]);
    (new OTPService($seller))->invalidate()->generate();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/2fa/enable', ['otp' => 1234])
        ->assertOk()
        ->assertJsonPath('message', 'Two-factor authentication enabled.');

    expect($seller->fresh()->twofa_enabled)->toBe(1);
    Mail::assertQueued(SellerTwoFactorEnabledMail::class);
});

test('enabling 2fa rejects an invalid otp', function () {
    $seller = Seller::factory()->verified()->create(['twofa_enabled' => 0]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/2fa/enable', ['otp' => 9999])
        ->assertBadRequest()
        ->assertJsonPath('message', 'Invalid or expired OTP.');

    expect($seller->fresh()->twofa_enabled)->toBe(0);
});

test('seller can disable 2fa with a valid otp', function () {
    $seller = Seller::factory()->verified()->withTwoFactor()->create();
    (new OTPService($seller))->invalidate()->generate();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/2fa/disable', ['otp' => 1234])
        ->assertOk()
        ->assertJsonPath('message', 'Two-factor authentication disabled.');

    expect($seller->fresh()->twofa_enabled)->toBe(0);
});

test('disabling 2fa rejects an invalid otp', function () {
    $seller = Seller::factory()->verified()->withTwoFactor()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/2fa/disable', ['otp' => 9999])
        ->assertBadRequest();

    expect($seller->fresh()->twofa_enabled)->toBe(1);
});
