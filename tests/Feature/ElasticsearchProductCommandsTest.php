<?php

use App\Models\Product\Item;
use App\Services\Elasticsearch\ProductIndexService;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('the product index commands are registered', function () {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('elasticsearch:products:reindex')
        ->toContain('elasticsearch:products:create-index')
        ->toContain('elasticsearch:products:delete-index')
        ->toContain('elasticsearch:products:index-one');
});

test('create index builds the index and points the alias at it', function () {
    $this->mock(ProductIndexService::class, function ($mock) {
        $mock->shouldReceive('createIndex')->once()->with(null)->andReturn('products_20261112');
        $mock->shouldReceive('swapAlias')->once()->with('products_20261112');
        $mock->shouldReceive('alias')->once()->andReturn('products');
    });

    $this->artisan('elasticsearch:products:create-index')
        ->expectsOutputToContain('Created product index: products_20261112')
        ->assertSuccessful();
});

test('index one removes a product that is not indexable', function () {
    $item = Item::factory()->create([
        'status' => Item::STATUS[Item::STATUS_DRAFT],
    ]);

    $this->mock(ProductIndexService::class, function ($mock) use ($item) {
        $mock->shouldReceive('documentRelations')->once()->andReturn([]);
        $mock->shouldReceive('isIndexable')->once()->andReturn(false);
        $mock->shouldReceive('deleteDocument')->once()->with($item->id);
    });

    $this->artisan('elasticsearch:products:index-one', ['id' => $item->id])
        ->expectsOutputToContain('removed from index')
        ->assertSuccessful();
});
