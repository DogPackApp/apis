<?php

use App\Models\Product\Item;
use App\Models\Product\ItemValueTranslation;
use Tests\Concerns\InteractsWithSellerAuth;
use Tests\Support\RecordingElasticsearchHttpClient;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('a title change deletes the stored title translation', function () {
    $item = Item::factory()->create([
        'title' => 'Old title',
        'description' => 'Old description',
    ]);

    ItemValueTranslation::query()->insert([
        [
            'item_id' => $item->id,
            'type' => ItemValueTranslation::TYPE_TITLE,
            'locale' => 'es',
            'value' => 'Titulo',
        ],
        [
            'item_id' => $item->id,
            'type' => ItemValueTranslation::TYPE_DESCRIPTION,
            'locale' => 'es',
            'value' => 'Descripcion',
        ],
    ]);

    $item->update(['title' => 'New title']);

    expect(ItemValueTranslation::query()->where('item_id', $item->id)->where('type', ItemValueTranslation::TYPE_TITLE)->exists())->toBeFalse()
        ->and(ItemValueTranslation::query()->where('item_id', $item->id)->where('type', ItemValueTranslation::TYPE_DESCRIPTION)->exists())->toBeTrue();
});

test('saving an active item writes it to the search index', function () {
    $item = Item::factory()->create([
        'status' => Item::STATUS[Item::STATUS_ACTIVE],
        'title' => 'Indexed toy',
        'manually_deactivated' => false,
    ]);

    $http = app(RecordingElasticsearchHttpClient::class);

    expect(collect($http->requests)->contains(
        fn (array $request): bool => str_contains($request['body'], 'Indexed toy') && str_contains($request['body'], (string) $item->id)
    ))->toBeTrue();
});

test('deleting an item removes it from the search index', function () {
    $item = Item::factory()->create([
        'status' => Item::STATUS[Item::STATUS_ACTIVE],
        'manually_deactivated' => false,
    ]);
    $http = app(RecordingElasticsearchHttpClient::class);
    $before = count($http->requests);

    $item->delete();

    expect(collect(array_slice($http->requests, $before))->contains(
        fn (array $request): bool => $request['method'] === 'DELETE' && str_contains($request['uri'], (string) $item->id)
    ))->toBeTrue();
});
