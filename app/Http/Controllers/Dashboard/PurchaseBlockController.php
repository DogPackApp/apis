<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order\StoreOrder;
use App\Models\Store\Store;
use Illuminate\Http\JsonResponse;

class PurchaseBlockController extends Controller
{
    public Store $store;

    public function __construct()
    {
        $this->store = resolve(Store::class);
    }

    public function __invoke(): JsonResponse
    {
        $orders = StoreOrder::query()
            ->where('store_id', $this->store->id)
            ->count();

        return response()->json([
            'orders' => $orders,
        ]);
    }
}
