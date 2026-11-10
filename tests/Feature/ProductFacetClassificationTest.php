<?php

use App\Jobs\Product\ClassifyItemToFacetsJob;
use App\Models\Product\Category;
use App\Models\Product\CategoryFacet;
use App\Models\Product\FacetValue;
use App\Models\Product\Item;
use App\Models\Product\ItemFacetClassificationLog;
use App\Models\Product\ItemFacetMapping;
use App\Services\Product\ItemFacetClassificationService;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('saving an active item classifies it into facet values', function () {
    $category = Category::query()->create([
        'name' => 'Toys',
        'slug' => 'facet-toys',
        'is_active' => true,
    ]);
    $facet = CategoryFacet::query()->create([
        'title' => 'Material',
        'category_id' => $category->id,
        'display_order' => 1,
        'is_active' => true,
    ]);
    $value = FacetValue::query()->create([
        'name' => 'Rubber',
        'category_facet_id' => $facet->id,
        'display_order' => 1,
        'is_active' => true,
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response(facetClassificationOpenAiResponse($facet->id, $value->id)),
    ]);

    $item = Item::factory()->create([
        'category_id' => $category->id,
        'status' => Item::STATUS[Item::STATUS_ACTIVE],
        'title' => 'Rubber toy',
        'manually_deactivated' => false,
    ]);

    expect(ItemFacetMapping::query()->where('item_id', $item->id)->pluck('facet_value_id')->all())
        ->toBe([$value->id])
        ->and(ItemFacetClassificationLog::query()->where('item_id', $item->id)->exists())->toBeTrue();
});

test('an inactive item loses its facet mappings', function () {
    $item = Item::factory()->create([
        'status' => Item::STATUS[Item::STATUS_DRAFT],
        'title' => 'Draft toy',
    ]);
    $facet = CategoryFacet::query()->create([
        'title' => 'Material',
        'display_order' => 1,
        'is_active' => true,
    ]);
    $value = FacetValue::query()->create([
        'name' => 'Rubber',
        'category_facet_id' => $facet->id,
        'display_order' => 1,
        'is_active' => true,
    ]);
    ItemFacetMapping::query()->insert([
        'item_id' => $item->id,
        'facet_value_id' => $value->id,
    ]);

    (new ClassifyItemToFacetsJob($item->id))->handle(app(ItemFacetClassificationService::class));

    expect(ItemFacetMapping::query()->where('item_id', $item->id)->exists())->toBeFalse();
});

test('the classify command writes facet mappings for one item', function () {
    $category = Category::query()->create([
        'name' => 'Treats',
        'slug' => 'facet-treats',
        'is_active' => true,
    ]);
    $facet = CategoryFacet::query()->create([
        'title' => 'Flavor',
        'category_id' => $category->id,
        'display_order' => 1,
        'is_active' => true,
    ]);
    $value = FacetValue::query()->create([
        'name' => 'Chicken',
        'category_facet_id' => $facet->id,
        'display_order' => 1,
        'is_active' => true,
    ]);
    $item = Item::factory()->create([
        'category_id' => $category->id,
        'status' => Item::STATUS[Item::STATUS_DRAFT],
        'title' => 'Chicken bites',
    ]);
    Item::query()->whereKey($item->id)->update([
        'status' => Item::STATUS[Item::STATUS_ACTIVE],
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response(facetClassificationOpenAiResponse($facet->id, $value->id)),
    ]);

    $this->artisan('marketplace:classify-item-facets', ['--item' => $item->id])
        ->assertSuccessful();

    expect(ItemFacetMapping::query()->where('item_id', $item->id)->pluck('facet_value_id')->all())
        ->toBe([$value->id]);
});

function facetClassificationOpenAiResponse(int $facetId, int $valueId): array
{
    return [
        'status' => 'completed',
        'output' => [[
            'content' => [[
                'type' => 'output_text',
                'text' => json_encode([
                    'mappings' => [[
                        'category_facet_id' => $facetId,
                        'facet_value_id' => $valueId,
                        'evidence' => 'Rubber toy',
                        'confidence' => 0.97,
                    ]],
                ]),
            ]],
        ]],
        'usage' => [
            'input_tokens' => 20,
            'output_tokens' => 10,
            'input_tokens_details' => ['cached_tokens' => 0],
        ],
    ];
}
