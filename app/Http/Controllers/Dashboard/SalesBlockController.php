<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order\OrderBilling;
use App\Models\Store\Store;
use Illuminate\Http\JsonResponse;

class SalesBlockController extends Controller
{
    public Store $store;

    public function __construct()
    {
        $this->store = resolve(Store::class);
    }

    public function __invoke(): JsonResponse
    {
        $orderBillings = OrderBilling::query()
            ->select(['id', 'store_order_id', 'total'])
            ->with([
                'order:id,store_id',
                'order.refunds:id,store_order_id,amount',
            ])
            ->whereHas('order', function ($query) {
                $query->where('store_id', $this->store->id ?? 0);
            })
            ->get();

        $totalSales = $orderBillings->sum('total') ?? 0;

        $totalRefunds = $orderBillings->sum(function ($billing) {
            return $billing->order->refunds->sum('amount');
        });

        $netSales = ($totalSales - $totalRefunds) / 100;

        return response()->json([
            'sales' => $netSales,
        ]);
    }
}
