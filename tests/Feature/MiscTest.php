<?php

use App\Models\Misc\Country;
use App\Models\Misc\Currency;
use App\Models\Misc\Language;
use App\Models\Misc\State;
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

test('state list requires authentication', function () {
    $this->getJson('/api/misc/state')->assertUnauthorized();
});

test('state list requires a country id', function () {
    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/misc/state')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['country_id']);
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

test('state list returns states for the requested country ordered by name', function () {
    Country::query()->create([
        'id' => 38,
        'short_name' => 'CA',
        'name' => 'Canada',
        'is_display' => 1,
        'order' => 2,
    ]);
    Country::query()->create([
        'id' => 231,
        'short_name' => 'US',
        'name' => 'United States',
        'is_display' => 1,
        'order' => 15,
    ]);

    State::query()->create(['id' => 68, 'country_id' => 38, 'short_name' => 'QC', 'name' => 'Quebec']);
    State::query()->create(['id' => 58, 'country_id' => 38, 'short_name' => 'AB', 'name' => 'Alberta']);
    State::query()->create(['id' => 6, 'country_id' => 231, 'short_name' => 'CA', 'name' => 'California']);

    $seller = Seller::factory()->verified()->create();
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->getJson('/api/misc/state?country_id=38')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', 58)
        ->assertJsonPath('data.0.name', 'Alberta')
        ->assertJsonPath('data.0.short_name', 'AB')
        ->assertJsonPath('data.1.name', 'Quebec')
        ->assertJsonMissing(['name' => 'California']);
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
