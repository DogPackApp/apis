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
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TotalSalesController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $driver = DB::connection()->getDriverName();
            $monthName = $driver === 'sqlite'
                ? "substr('JanFebMarAprMayJunJulAugSepOctNovDec', (cast(strftime('%m', store_orders.created_at) as integer) - 1) * 3 + 1, 3)"
                : 'DATE_FORMAT(store_orders.created_at, "%b")';
            $monthNumber = $driver === 'sqlite'
                ? "strftime('%m', store_orders.created_at)"
                : 'DATE_FORMAT(store_orders.created_at, "%m")';
            $monthGroup = $driver === 'sqlite'
                ? "strftime('%Y-%m', store_orders.created_at)"
                : 'DATE_FORMAT(store_orders.created_at, "%Y-%m")';

            $query = StoreOrder::query()
                ->where('store_id', resolve(Store::class)->id)
                ->where('fulfilment_status', StoreOrder::ORDER_FULFILLED)
                ->where('store_orders.created_at', '>=', now()->subMonths(6))
                ->join('order_billings', 'order_billings.store_order_id', '=', 'store_orders.id')
                ->selectRaw("
                                {$monthName} as month_name,
                                {$monthNumber} as month_number,
                                SUM(order_billings.subtotal) as total_sales
                            ")
                ->groupByRaw($monthGroup)
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
