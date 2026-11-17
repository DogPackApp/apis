<?php

namespace App\Services\Product;

use App\Models\Product\ItemPrice;
use App\Models\Product\ItemVariant;
use App\Models\Product\ProductFieldOwnership;
use Illuminate\Support\Collection;

class ProductListBatch
{
    /**
     * @param  array<int, true>  $itemIds
     * @param  array<int, int|string>  $priceStock
     * @param  array<int, int|string>  $variantStock
     * @param  array<int, int|string>  $variantCount
     * @param  array<int, array<string, true>>  $dogpackFields
     */
    public function __construct(
        public array $itemIds = [],
        public array $priceStock = [],
        public array $variantStock = [],
        public array $variantCount = [],
        public array $dogpackFields = [],
    ) {}

    /**
     * @param  Collection<int, mixed>  $items
     */
    public static function fromItems(Collection $items): self
    {
        $ids = $items->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($ids === []) {
            return new self;
        }

        $priceStock = ItemPrice::query()
            ->whereIn('item_id', $ids)
            ->pluck('stock_quantity', 'item_id')
            ->all();

        $variantTotals = ItemVariant::query()
            ->selectRaw('item_id, SUM(stock_quantity) as stock_total, COUNT(*) as variant_count')
            ->whereIn('item_id', $ids)
            ->groupBy('item_id')
            ->get();

        $variantStock = [];
        $variantCount = [];

        foreach ($variantTotals as $total) {
            $variantStock[(int) $total->item_id] = $total->stock_total;
            $variantCount[(int) $total->item_id] = $total->variant_count;
        }

        $dogpackFields = [];

        ProductFieldOwnership::query()
            ->whereIn('item_id', $ids)
            ->where('ownership', ProductFieldOwnership::PRODUCT_FIELD_OWNERSHIP['dogpack'])
            ->get(['item_id', 'field_name'])
            ->each(function (ProductFieldOwnership $ownership) use (&$dogpackFields): void {
                $dogpackFields[(int) $ownership->item_id][$ownership->field_name] = true;
            });

        return new self(
            itemIds: array_fill_keys($ids, true),
            priceStock: $priceStock,
            variantStock: $variantStock,
            variantCount: $variantCount,
            dogpackFields: $dogpackFields,
        );
    }

    public function has(int $itemId): bool
    {
        return isset($this->itemIds[$itemId]);
    }
}
