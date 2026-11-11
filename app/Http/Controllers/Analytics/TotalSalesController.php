<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Resources\Analytics\TotalSalesResource;
use App\Models\Order\StoreOrder;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class TotalSalesController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $query = StoreOrder::query()
                ->where('store_id', resolve(Store::class)->id)
                ->where('fulfilment_status', StoreOrder::ORDER_FULFILLED)
                ->where('store_orders.created_at', '>=', now()->subMonths(6))
                ->join('order_billings', 'order_billings.store_order_id', '=', 'store_orders.id')
                ->selectRaw('
                                DATE_FORMAT(store_orders.created_at, "%b") as month_name,
                                DATE_FORMAT(store_orders.created_at, "%m") as month_number,
                                SUM(order_billings.subtotal) as total_sales
                            ')
                ->groupByRaw('DATE_FORMAT(store_orders.created_at, "%Y-%m")')
                ->orderBy('month_number')
                ->get();

            return response()->json([
                'sales' => TotalSalesResource::collection($query),
                'total_sales' => $query->sum('total_sales') / 100,
            ]);
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
