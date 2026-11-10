<?php

use App\Models\Product\Item;
use App\Models\Seller\Seller;
use App\Models\Store\Policy;
use App\Models\Store\Store;
use App\Services\OnboardingService;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

function policySellerWithStore(): array
{
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    return [$seller, $store];
}

test('policy routes require authentication', function () {
    $this->getJson('/api/seller/policy')->assertUnauthorized();
    $this->postJson('/api/seller/policy', ['name' => 'Returns'])->assertUnauthorized();
});

test('the first policy is the default and completes store settings', function () {
    [$seller, $store] = policySellerWithStore();
    (new OnboardingService)->initiateOnboarding($seller);

    $item = Item::factory()->create([
        'store_id' => $store->id,
        'policy_id' => null,
    ]);

    $this->postJson('/api/seller/policy', [
        'name' => 'Standard',
        'is_return' => true,
        'time' => 14,
        'custom_text' => 'Fourteen days',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Standard')
        ->assertJsonPath('data.is_return', 1)
        ->assertJsonPath('data.is_exchange', 0)
        ->assertJsonPath('data.is_default', 1)
        ->assertJsonPath('data.time', 14)
        ->assertJsonPath('data.custom_text', 'Fourteen days');

    expect($item->fresh()->policy_id)->not->toBeNull()
        ->and((new OnboardingService)->fetchOnboardingStatus($seller)->is_store_setting)->toBe(1);
});

test('a later policy stays non-default until it is toggled', function () {
    policySellerWithStore();

    $this->postJson('/api/seller/policy', ['name' => 'First', 'time' => null])->assertCreated();

    $second = $this->postJson('/api/seller/policy', [
        'name' => 'Second',
        'time' => null,
    ])->assertCreated()
        ->assertJsonPath('data.is_default', 0)
        ->json('data.uuid');

    $this->putJson('/api/seller/policy/'.$second.'/default')
        ->assertOk()
        ->assertExactJson([]);

    $this->getJson('/api/seller/policy')
        ->assertOk()
        ->assertJsonPath('data.0.is_default', 0)
        ->assertJsonPath('data.1.is_default', 1);
});

test('policy create validates the name and return window', function () {
    policySellerWithStore();

    $this->postJson('/api/seller/policy', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->postJson('/api/seller/policy', [
        'name' => 'Returns',
        'is_return' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['time']);
});

test('a store cannot have more than four policies', function () {
    policySellerWithStore();

    foreach (['One', 'Two', 'Three', 'Four'] as $name) {
        $this->postJson('/api/seller/policy', ['name' => $name, 'time' => null])->assertCreated();
    }

    $this->postJson('/api/seller/policy', ['name' => 'Five', 'time' => null])
        ->assertUnprocessable()
        ->assertJsonPath('errors', 'Cannot Create More than 4 Policies');
});

test('seller can show and rename a policy', function () {
    policySellerWithStore();

    $uuid = $this->postJson('/api/seller/policy', ['name' => 'Old', 'time' => null])
        ->assertCreated()
        ->json('data.uuid');

    $this->getJson('/api/seller/policy/'.$uuid)
        ->assertOk()
        ->assertJsonPath('data.name', 'Old')
        ->assertJsonPath('data.uuid', $uuid);

    $this->putJson('/api/seller/policy/'.$uuid, ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');
});

test('the only default policy cannot be unset or deleted', function () {
    policySellerWithStore();

    $uuid = $this->postJson('/api/seller/policy', ['name' => 'Only', 'time' => null])
        ->json('data.uuid');

    $this->putJson('/api/seller/policy/'.$uuid, ['is_default' => false])
        ->assertUnprocessable()
        ->assertJsonPath('errors', 'You must have at least one default policy. Please set another default policy before removing this one.');

    $this->deleteJson('/api/seller/policy/'.$uuid)
        ->assertUnprocessable()
        ->assertJsonPath('errors', 'Default policy cannot be deleted');
});

test('deleting a policy moves its items onto the default policy', function () {
    [, $store] = policySellerWithStore();

    $default = $this->postJson('/api/seller/policy', ['name' => 'Default', 'time' => null])
        ->json('data.uuid');

    $other = $this->postJson('/api/seller/policy', ['name' => 'Other', 'time' => null])
        ->json('data.uuid');

    $item = Item::factory()->create(['store_id' => $store->id]);

    $this->postJson('/api/seller/policy/'.$other.'/items', [
        'items' => [(string) $item->uuid],
    ])->assertNoContent();

    expect($item->fresh()->policy_id)->not->toBeNull();

    $this->deleteJson('/api/seller/policy/'.$other)
        ->assertOk()
        ->assertJsonPath('message', 'Policy deleted successfully');

    $defaultId = Policy::query()->where('uuid', $default)->value('id');

    expect($item->fresh()->policy_id)->toBe($defaultId);
});

test('policy items return the product uuid', function () {
    [, $store] = policySellerWithStore();

    $uuid = $this->postJson('/api/seller/policy', ['name' => 'With items', 'time' => null])
        ->json('data.uuid');

    $item = Item::factory()->create(['store_id' => $store->id, 'policy_id' => null]);

    $this->postJson('/api/seller/policy/'.$uuid.'/items', [
        'items' => [(string) $item->uuid],
    ])->assertNoContent();

    $this->getJson('/api/seller/policy/'.$uuid.'/items')
        ->assertOk()
        ->assertJsonPath('data.items.0.uuid', (string) $item->uuid);
});
