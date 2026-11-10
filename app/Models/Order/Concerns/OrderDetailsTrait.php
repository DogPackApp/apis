<?php

namespace App\Models\Order\Concerns;

use App\Models\Order\OrderItem;
use App\Models\Order\StoreOrder;

trait OrderDetailsTrait
{
    public function areOtherItemFulfilled($order, $itemIds, $itemType): bool
    {
        // for the given order, get all the items and the fulfilment status of the items
        // load missing with few selected fields
        $order->loadMissing('items:id,uuid,store_order_id,fulfilment_status');

        foreach ($order->items as $item) {
            if (in_array($item->uuid, $itemIds)) {
                continue;
            }

            if ($item->fulfilment_status !== StoreOrder::ORDER_FULFILLED) {
                return false;
            }
        }

        return true;
    }

    public function areItemsFulfilled($order, $itemIds): bool
    {
        // for the given order, get all the items and the fulfilment status of the items
        // load missing with few selected fields
        $order->loadMissing('items:id,uuid,store_order_id,fulfilment_status');

        foreach ($order->items as $item) {
            if (! in_array($item->uuid, $itemIds)) {
                continue;
            }

            if ($item->fulfilment_status !== StoreOrder::ORDER_FULFILLED) {
                return false;
            }
        }

        return true;
    }

    public function getDigitalItems($order): array
    {
        $digitalItems = [];

        // i want to identify if the order has any digital products
        $order->loadMissing([
            'items:id,uuid,store_order_id,fulfilment_status,product_id',
            'items.product:id,title,type',
        ]);

        foreach ($order->items as $item) {
            // type = 1 / Digital Product
            if ($item->product->type == 1) {
                $digitalItems[] = $item->uuid;
            }
        }

        return $digitalItems;
    }

    public function hasDigitalItemFulfilled($order): bool
    {
        $digitalItems = $this->getDigitalItems($order);

        if (empty($digitalItems)) {
            // returning true here means: there is no digital items available
            return true;
        }

        if ($this->areItemsFulfilled($order, $digitalItems)) {
            // this means all the digital items are fulfilled
            return true;
        }

        return false;
    }

    public function markItemAsFulfilled(StoreOrder $order, $items): void
    {
        OrderItem::query()
            ->where('uuid', $items)
            ->where('store_order_id', $order->id)
            ->update(['fulfilment_status' => StoreOrder::ORDER_FULFILLED]);
    }
}
