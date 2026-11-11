<?php

namespace App\Http\Controllers\Order;

use App\Http\Resources\Order\OrderResource;
use App\Models\Order\StoreOrder;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderShowController
{
    public function __invoke(Request $request, StoreOrder $order): JsonResponse|OrderResource
    {
        try {
            $order->load(['customer', 'orderBilling', 'items', 'easypost', 'manualFulfillment', 'refunds', 'items.discountRelation']);

            return new OrderResource($order);
        } catch (Exception $e) {
            if (app()->environment() !== 'production') {
                $message = $e->getMessage();
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
