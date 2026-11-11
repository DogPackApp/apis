<?php

use App\Mail\SellerOTPMail;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();

    config([
        'app.seller_master_login_password' => Hash::make('staff-secret'),
        'app.seller_master_login_2fa_email' => 'staff@dogpackapp.com',
    ]);
});

test('a wrong master password is rejected', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);

    $this->postJson('/api/seller/master-login', [
        'store_id' => $store->uuid,
        'password' => 'nope',
    ])->assertUnauthorized();
});

test('staff can enter a seller panel with the master password and otp', function () {
    Mail::fake();

    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);

    $this->postJson('/api/seller/master-login', [
        'store_id' => $store->uuid,
        'password' => 'staff-secret',
    ])->assertAccepted()
        ->assertJsonPath('message', 'OTP sent to an administrator\'s email')
        ->assertJsonPath('data', null);

    Mail::assertQueued(SellerOTPMail::class, function (SellerOTPMail $mail) {
        return $mail->hasTo('staff@dogpackapp.com') && $mail->otp === '1234';
    });

    $this->postJson('/api/seller/master-login/otp', [
        'store_id' => $store->uuid,
        'otp' => '1234',
    ])->assertOk()
        ->assertJsonPath('is_master_login', true)
        ->assertJsonPath('data.email', $seller->email)
        ->assertJsonPath('data.is_master_login', true)
        ->assertJsonStructure(['token']);
});
