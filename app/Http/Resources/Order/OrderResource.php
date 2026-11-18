<?php

namespace App\Http\Resources\Order;

use App\Models\Order\EasyPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'order_no' => $this->order_no,
            'status' => $this->status ?? EasyPost::UNKNOWN,
            'payment_status' => $this->payment_status,
            'order_status' => $this->order_status,
            'fulfilment_status' => $this->fulfilment_status,
            'dispute' => $this->disputeApiStatus(),
            'shipping_method' => $this->shipping_method,
            'shipping' => $this->shipping,
            'date' => $this->created_at,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'orderBilling' => new SubtotalResource($this->whenLoaded('orderBilling')),
            'items' => ItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->items_sum_quantity ?? null,
            'easypost' => new EasyPostResource($this->whenLoaded('easypost')),
            'manualFulfillment' => new ManualFulfillmentResource($this->whenLoaded('manualFulfillment')),
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),
        ];
    }
}
