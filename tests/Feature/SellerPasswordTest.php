<?php

use App\Mail\SellerForgotPasswordEmail;
use App\Mail\SellerPasswordUpdatedMail;
use App\Models\Seller\Seller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('forgot password requires an existing seller email', function () {
    $this->postJson('/api/seller/password/forgot', [
        'email' => 'missing@example.com',
    ])->assertUnprocessable();
});

test('forgot password queues a reset email', function () {
    Mail::fake();

    $seller = Seller::factory()->verified()->create([
        'email' => 'seller@example.com',
    ]);

    $this->postJson('/api/seller/password/forgot', [
        'email' => $seller->email,
    ])->assertOk()
        ->assertJsonPath('message', 'Reset link sent to your email.');

    Mail::assertQueued(SellerForgotPasswordEmail::class);
});

test('seller can reset their password and receives a confirmation email', function () {
    Mail::fake();

    $seller = Seller::factory()->verified()->create([
        'email' => 'seller@example.com',
    ]);

    $token = Password::broker('sellers')->createToken($seller);

    $this->postJson('/api/seller/password/reset', [
        'email' => $seller->email,
        'token' => $token,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertOk();

    expect(Hash::check('new-password', $seller->fresh()->password))->toBeTrue();

    Mail::assertQueued(SellerPasswordUpdatedMail::class);
});

test('password reset rejects an invalid token', function () {
    $seller = Seller::factory()->verified()->create();

    $this->postJson('/api/seller/password/reset', [
        'email' => $seller->email,
        'token' => 'invalid-token',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertBadRequest();
});
