<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Models\Order\StoreOrder;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class UnfulfilledBlockController extends Controller
{
    use RespondsWithSellerJson;

    public Store $store;

    public function __construct()
    {
        $this->store = resolve(Store::class);
    }

    public function __invoke(): JsonResponse
    {
        try {
            $flatten = [];

            StoreOrder::query()
                ->with(['customer:id,name,store_order_id', 'orderBilling:id,total,store_order_id'])
                ->select(['order_no', 'created_at', 'id', 'uuid'])
                ->withSum('items', 'quantity')
                ->where('store_id', $this->store->id)
                ->where('fulfilment_status', StoreOrder::ORDER_UNFULFILLED)
                ->orderBy('created_at', 'desc')
                ->limit(request()->input('limit', 5))
                ->get()
                ->each(function ($order) use (&$flatten) {
                    $flatten[] = [
                        'customer' => $order->customer->name,
                        'itemsCount' => $order->items_sum_quantity,
                        'created_at' => $order->created_at,
                        'order_no' => $order->order_no,
                        'total' => $order->orderBilling->total / 100,
                        'uuid' => $order->uuid,
                    ];
                });

            return $this->success($flatten);
        } catch (Exception $exception) {
            $this->errorResponse($exception->getMessage(), ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
