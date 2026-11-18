<?php

namespace App\Http\Controllers\Order;

use App\Http\Resources\Order\OrderResource;
use App\Models\Order\StoreOrder;
use App\Models\Product\Item;
use App\Models\Product\ItemVariant;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class OrderUpdateProductWeightController
{
    public function __invoke(Request $request, StoreOrder $order): JsonResponse|OrderResource
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                '*.weight' => ['required', 'numeric'],
                '*.unit' => ['required', 'string', 'in:'.implode(',', array_keys(Item::ITEM_WEIGHT))],
                '*.variant_uuid' => ['nullable', 'string'],
                '*.product_uuid' => ['required', 'string'],
                '*.uuid' => ['required', 'string'],
                '*.is_product_update' => ['required', 'boolean'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $order->load(relations: ['items.product.itemPrices', 'items.product.varyBy']);

            $items = [];

            foreach ($validator->validated() as $product) {
                if ($product['variant_uuid']) { //
                    $items[$product['product_uuid']][] = $product;
                }
            }

            foreach ($validator->validated() as $item) {

                $orderItem = $order->items->firstWhere('uuid', $item['uuid']);

                if (! $orderItem) {
                    continue;
                }

                $varyByWeight = $orderItem->product->varyBy->weight ?? 0;

                $productSnapshot = $orderItem->product_snapshot;

                $productSnapshot['weight']['single'] = $item['weight'];
                $productSnapshot['weight']['total'] = $item['weight'] * $orderItem->quantity ?? 1;
                $productSnapshot['weight']['unit'] = $item['unit'];
                $orderItem->product_snapshot = $productSnapshot;

                if ($item['is_product_update']) {
                    if ($item['variant_uuid'] && $varyByWeight) {
                        $itemVariant = ItemVariant::where('uuid', $item['variant_uuid'])->first();
                        $itemVariant->weight = $item['weight'];

                        $fromUnit = array_search($orderItem->product->itemPrices->unit, Item::ITEM_WEIGHT) ?? 'G';

                        $this->convertAllVariantsWeight(
                            $orderItem->product_id,
                            $item['unit'],
                            $fromUnit
                        );

                        $itemVariant->save();
                    } else {
                        $orderItem->product->itemPrices->weight = $item['weight'] * 100;
                    }

                    $orderItem->product->itemPrices->unit = Item::ITEM_WEIGHT[$item['unit']] ?? 1;
                    $orderItem->product->itemPrices->save();
                }

                $orderItem->save();
            }

            DB::commit();

            return response()->json([], Response::HTTP_OK);
        } catch (Exception $e) {
            DB::rollBack();

            if (app()->environment() !== 'production') {
                $message = $e->getMessage();
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function convertAllVariantsWeight($productId, $unit, $fromUnit): void
    {
        $itemVariants = ItemVariant::query()
            ->select('id', 'weight')
            ->where('item_id', $productId)
            ->get();

        foreach ($itemVariants as $itemVariant) {
            $itemVariant->weight = round($this->convertWeight($itemVariant->weight, $unit, $fromUnit), 2);
            $itemVariant->save();
        }
    }

    // convert the below function to a method
    public function convertWeight($value, string $toUnit, string $fromUnit): float
    {
        // Conversion rates to grams
        $toGrams = [
            'MG' => 0.001,
            'G' => 1,
            'KG' => 1000,
            'OZ' => 28.3495,
            'LB' => 453.592,
        ];

        $fromUnit = strtoupper($fromUnit);
        $toUnit = strtoupper($toUnit);

        if (! isset($toGrams[$fromUnit]) || ! isset($toGrams[$toUnit])) {
            return $value;
        }

        // Normalize to grams
        $grams = $value * $toGrams[$fromUnit];

        // Convert grams → target
        return $grams / $toGrams[$toUnit];
    }
}
