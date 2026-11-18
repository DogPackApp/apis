<?php

namespace App\Models\Order\Concerns;

use App\Models\Order\StoreOrder;

trait UpdateOrderStatus
{
    public ?StoreOrder $updateOrderStatus = null;

    public function markAsUnfulfilled(StoreOrder $order): static
    {
        $this->updateOrderStatus = $order;
        $order->fulfilment_status = StoreOrder::ORDER_UNFULFILLED;
        $order->fulfilment_date = now();

        return $this;
    }

    public function markAsPartiallyFulfilled(StoreOrder $order): static
    {
        $this->updateOrderStatus = $order;
        $order->fulfilment_status = StoreOrder::ORDER_PARTIALLY_FULFILLED;

        return $this;
    }

    public function markAsFulfilled(StoreOrder $order): static
    {
        $this->updateOrderStatus = $order;
        $order->fulfilment_status = StoreOrder::ORDER_FULFILLED;
        $order->fulfilment_date = now();

        return $this;
    }

    public function markAsDelivered(StoreOrder $order): static
    {
        $this->updateOrderStatus = $order;
        $order->order_status = StoreOrder::ORDER_STATUS_DELIVERED;

        return $this;
    }

    public function markOrderStatus(StoreOrder $order, string $status): static
    {
        $this->updateOrderStatus = $order;
        $order->order_status = $status;

        return $this;
    }

    public function save(): void
    {
        $this->updateOrderStatus->save();
    }
}
