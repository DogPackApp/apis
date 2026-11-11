<?php

use App\Models\Seller\Seller;
use App\Models\Store\Store;
use App\Models\SubscribeSave\SubscribeSaveSetting;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
    Cache::flush();
});

test('a seller sees the default subscribe and save percents', function () {
    $seller = Seller::factory()->verified()->create();
    Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/seller/store/subscribe-save/allowed-percents')
        ->assertOk()
        ->assertJsonPath('subscribe_save_allowed_percents', [5, 10, 15, 20, 25, 30, 35, 40, 45, 50]);
});

test('a seller sees the percents saved for the platform', function () {
    $seller = Seller::factory()->verified()->create();
    Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    SubscribeSaveSetting::query()->create([
        'dogpack_first_order_percent' => 5,
        'allowed_seller_percents' => [10, 20],
        'allowed_frequencies_days' => [14],
        'threshold_percent' => ['ca' => 30, 'skip' => 'nope'],
        'max_discount_amount' => ['US' => 20],
    ]);

    $this->getJson('/api/seller/store/subscribe-save/allowed-percents')
        ->assertOk()
        ->assertJsonPath('subscribe_save_allowed_percents', [10, 20]);
});
