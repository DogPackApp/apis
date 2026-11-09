<?php

namespace App\Http\Controllers\Seller\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\ShippingZoneResource;
use App\Models\Store\ShippingZone;
use App\Models\Store\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ShippingShowController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $shippingZone = ShippingZone::query()
            ->with([
                'shippingZoneCountry.country',
                'shippingZoneCountry.excludedStates',
                'shippingZoneRates.currency',
                'shippingZoneRates.shippingRate',
            ])
            ->where('store_id', resolve(Store::class)->id)
            ->latest()
            ->get();

        if (is_null($shippingZone)) {
            return response()->json([
                'message' => 'Shipping zone not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return ShippingZoneResource::collection($shippingZone);
    }
}
