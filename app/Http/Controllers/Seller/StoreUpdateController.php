<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreUpdateRequest;
use App\Http\Resources\Store\StoreResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class StoreUpdateController extends Controller
{
    public function __invoke(StoreUpdateRequest $request): JsonResponse
    {
        $store = $request->user()->store;

        if (! $store) {
            return response()->json(['message' => 'Store not found.'], Response::HTTP_NOT_FOUND);
        }

        $store->fill($request->validated());
        $store->save();

        return StoreResource::make($store)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
