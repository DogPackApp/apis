<?php

use App\Models\Product\Gallery;
use App\Models\Product\Item;
use App\Models\Product\ItemMedia;
use App\Models\Seller\Seller;
use App\Models\Store\Section;
use App\Models\Store\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

function actingSellerWithStore(): array
{
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    return [$seller, $store];
}

function makeSection(Store $store, string $name, int $order): Section
{
    $section = new Section;
    $section->store_id = $store->id;
    $section->name = $name;
    $section->slug = str($name)->slug()->toString();
    $section->is_visible = 1;
    $section->order = $order;
    $section->save();

    return $section;
}

test('section routes require authentication', function () {
    $this->getJson('/api/marketplace/store/section')->assertUnauthorized();
    $this->postJson('/api/marketplace/store/section', ['name' => 'Dogs'])->assertUnauthorized();
});

test('seller can create and list sections', function () {
    actingSellerWithStore();

    $this->postJson('/api/marketplace/store/section', [
        'name' => 'Best Sellers',
        'is_visible' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Best Sellers')
        ->assertJsonPath('data.slug', 'best-sellers')
        ->assertJsonPath('data.is_visible', 1)
        ->assertJsonPath('data.order', 1);

    $this->postJson('/api/marketplace/store/section', [
        'name' => 'New',
    ])->assertCreated()
        ->assertJsonPath('data.is_visible', 0)
        ->assertJsonPath('data.order', 2);

    $this->getJson('/api/marketplace/store/section')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Best Sellers')
        ->assertJsonPath('data.1.name', 'New');
});

test('an unreadable section cache is rebuilt without replacing the shared key', function () {
    [, $store] = actingSellerWithStore();
    makeSection($store, 'Best Sellers', 1);

    $key = "store_sections_{$store->id}";
    $shared = unserialize(
        'O:39:"Illuminate\\Database\\Eloquent\\Collection":0:{}',
        ['allowed_classes' => false],
    );
    Cache::forever($key, $shared);

    $this->getJson('/api/marketplace/store/section')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Best Sellers')
        ->assertJsonPath('data.0.items_count', 0);

    $cached = Cache::get($key.'.apis');

    expect(Cache::get($key))->toBe($shared)
        ->and($cached)->toBeInstanceOf(Collection::class)
        ->and($cached->first())->toBeInstanceOf(Section::class)
        ->and($cached->first()->name)->toBe('Best Sellers')
        ->and(unserialize(serialize($cached), ['allowed_classes' => true])->first()->name)->toBe('Best Sellers');
});

test('section create validates the name', function () {
    actingSellerWithStore();

    $this->postJson('/api/marketplace/store/section', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('seller can rename a section', function () {
    [, $store] = actingSellerWithStore();
    $section = makeSection($store, 'Old', 1);

    $this->putJson('/api/marketplace/store/section/'.$section->uuid, ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.slug', 'renamed');
});

test('seller can add and remove items and fetch the section', function () {
    [, $store] = actingSellerWithStore();
    $section = makeSection($store, 'Dogs', 1);
    $item = Item::factory()->create(['store_id' => $store->id]);

    $gallery = new Gallery;
    $gallery->store_id = $store->id;
    $gallery->url = 'uploads/products/cover.webp';
    $gallery->type = 'image/webp';
    $gallery->save();

    ItemMedia::query()->create([
        'item_id' => $item->id,
        'gallery_id' => $gallery->id,
        'order' => 0,
    ]);

    $this->postJson('/api/marketplace/store/section/'.$section->uuid.'/items', [
        'items' => [$item->uuid],
    ])->assertNoContent();

    expect($section->items()->pluck('items.id')->all())->toBe([$item->id]);

    $response = $this->getJson('/api/marketplace/store/section/'.$section->uuid.'/items')
        ->assertOk()
        ->assertJsonPath('data.uuid', (string) $section->uuid)
        ->assertJsonPath('data.items.0.uuid', (string) $item->uuid)
        ->assertJsonPath('data.items.0.title', $item->title)
        ->assertJsonPath('data.items.0.media_url', $gallery->url)
        ->assertJsonPath('data.items.0.media.url', $gallery->url)
        ->assertJsonPath('data.items.0.sections.0.uuid', (string) $section->uuid)
        ->assertJsonPath('data.items.0.sections.0.name', 'Dogs')
        ->assertJsonPath('data.items.0.sections.0.slug', $section->slug);

    expect(array_keys($response->json('data.items.0')))->toBe(['uuid', 'title', 'media_url', 'media', 'sections'])
        ->and(array_keys($response->json('data.items.0.sections.0')))->toBe(['uuid', 'name', 'slug', 'is_visible']);

    $this->deleteJson('/api/marketplace/store/section/'.$section->uuid.'/items', [
        'items' => [$item->uuid],
    ])->assertNoContent();

    expect($section->items()->count())->toBe(0);
});

test('deleting a section detaches its items', function () {
    [, $store] = actingSellerWithStore();
    $section = makeSection($store, 'Dogs', 1);
    $item = Item::factory()->create(['store_id' => $store->id]);
    $section->items()->attach($item->id, ['order' => 0]);

    $this->deleteJson('/api/marketplace/store/section/'.$section->uuid)->assertNoContent();

    expect(Section::query()->find($section->id))->toBeNull()
        ->and($section->items()->count())->toBe(0);
});

test('reordering items inside a section hits the same pivot update as backend-api', function () {
    [, $store] = actingSellerWithStore();
    $section = makeSection($store, 'Dogs', 1);
    $first = Item::factory()->create(['store_id' => $store->id]);
    $second = Item::factory()->create(['store_id' => $store->id]);
    $section->items()->attach($first->id, ['order' => 0]);
    $section->items()->attach($second->id, ['order' => 1]);

    $this->putJson('/api/marketplace/store/section/'.$section->uuid.'/items/reorder', [
        'data' => [
            ['uuid' => $second->uuid, 'order' => 0],
        ],
    ])->assertUnprocessable()
        ->assertJsonPath('errors.message', fn (string $message) => str_contains($message, 'no such column: order'));
});

test('moving items uses the same request keys as backend-api', function () {
    [, $store] = actingSellerWithStore();
    $from = makeSection($store, 'From', 1);
    $to = makeSection($store, 'To', 2);
    $item = Item::factory()->create(['store_id' => $store->id]);
    $from->items()->attach($item->id, ['order' => 0]);

    $this->putJson('/api/marketplace/store/section/'.$from->uuid.'/move', [
        'items' => [$item->uuid],
        'sections' => [$to->uuid],
    ])->assertNoContent();

    expect($to->items()->pluck('items.id')->all())->toBe([$item->id])
        ->and($from->items()->count())->toBe(0);
});
