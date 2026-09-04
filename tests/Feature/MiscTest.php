<?php

use App\Models\Misc\Country;
use App\Models\Misc\Currency;
use App\Models\Misc\Language;
use App\Models\Seller\Seller;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('country list requires authentication', function () {
    $this->getJson('/api/misc/country')->assertUnauthorized();
});

test('currency list requires authentication', function () {
    $this->getJson('/api/misc/currency')->assertUnauthorized();
});

test('language list requires authentication', function () {
    $this->getJson('/api/misc/language')->assertUnauthorized();
});

test('country list returns the expected shape', function () {
    Country::query()->create([
        'id' => 38,
        'short_name' => 'CA',
        'name' => 'Canada',
        'currency_code' => 'CAD',
        'currency_name' => 'Canadian Dollar',
        'currency_symbol' => 'CA$',
        'is_display' => 1,
        'is_display_front' => 1,
        'is_display_mobile' => 1,
        'order' => 2,
    ]);

    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/misc/country')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                ['id', 'name', 'short_name', 'currency_code', 'currency_name', 'currency_symbol', 'is_display', 'flag'],
            ],
        ])
        ->assertJsonPath('data.0.id', 38)
        ->assertJsonPath('data.0.name', 'Canada')
        ->assertJsonPath('data.0.short_name', 'CA')
        ->assertJsonPath('data.0.currency_code', 'CAD')
        ->assertJsonPath('data.0.is_display', 1)
        ->assertJsonPath('data.0.flag', fn ($flag) => str_ends_with($flag, '/countryflags/ca.webp'));
});

test('currency list returns the misc shape with currency fields always null', function () {
    Currency::query()->create(['id' => 1, 'name' => 'CAD']);

    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/misc/currency')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                ['id', 'name', 'currency_code', 'currency_name', 'currency_symbol'],
            ],
        ])
        ->assertJsonPath('data.0.name', 'CAD')
        ->assertJsonPath('data.0.currency_code', null)
        ->assertJsonPath('data.0.currency_name', null)
        ->assertJsonPath('data.0.currency_symbol', null);
});

test('language list returns the misc shape with currency fields always null', function () {
    Language::query()->create(['id' => 1, 'name' => 'English', 'short_name' => 'en']);

    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/misc/language')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'English')
        ->assertJsonPath('data.0.currency_code', null);
});
