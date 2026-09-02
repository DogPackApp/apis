<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\StoreResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StoreShowController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $store = $request->user()->store;

        if (! $store) {
            return response()->json(['message' => 'Store not found.'], Response::HTTP_NOT_FOUND);
        }

        return StoreResource::make($store)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
