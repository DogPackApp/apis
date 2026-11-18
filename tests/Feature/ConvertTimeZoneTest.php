<?php

use App\Models\Seller\Seller;
use App\Models\Store\Section;
use App\Models\Store\Store;
use App\Services\TimezoneService;
use Laravel\Passport\Passport;
use ReflectionProperty;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();

    $property = new ReflectionProperty(TimezoneService::class, 'store');
    $property->setValue(null, null);
});

test('model timestamps use the store timezone', function () {
    $seller = Seller::factory()->verified()->create();
    Store::factory()->create([
        'seller_id' => $seller->id,
        'timezone' => 'America/New_York',
    ]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $section = Section::query()->forceCreate([
        'store_id' => resolve(Store::class)->id,
        'name' => 'Winter',
        'slug' => 'winter',
        'is_visible' => 1,
        'order' => 1,
    ]);
    $section->created_at = '2026-01-15 15:00:00';
    $section->save();

    $createdAt = $section->fresh()->created_at;

    expect($createdAt->timezoneName)->toBe('America/New_York')
        ->and($createdAt->format('H:i'))->toBe('10:00')
        ->and($createdAt->jsonSerialize())->toBe('2026-01-15T10:00:00-05:00');
});
