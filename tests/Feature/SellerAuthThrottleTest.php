<?php

use App\Models\Seller\Seller;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('seller login is throttled after too many attempts', function () {
    $seller = Seller::factory()->verified()->create([
        'email' => 'throttled-seller@example.com',
    ]);

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/seller/login', [
            'email' => $seller->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/seller/login', [
        'email' => $seller->email,
        'password' => 'wrong-password',
    ])->assertStatus(429);
});
