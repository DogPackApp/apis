<?php

namespace App\Http\Controllers\Seller\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\ShippingRatesResource;
use App\Models\Store\ShippingRate;
use App\Models\Store\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShippingRatesShowController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $store = resolve(Store::class);

        $shippingRates = ShippingRate::query()
            ->where('store_id', $store->id)
            ->orWhereNull('store_id')
            ->orderBy('created_at', 'asc')
            ->get();

        return ShippingRatesResource::collection($shippingRates);
    }
}
