<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\AddressResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class StoreAddressShowController extends Controller
{
    #[OA\Get(
        path: '/api/seller/store/address',
        summary: "Get the authenticated seller's store address",
        security: [['sellerAuth' => []]],
        tags: ['Store'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Store shipping address, or an empty data object when none is saved',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/StoreAddress', nullable: true)]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'The seller has not created a store yet',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Store not found.')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $store = $request->user()->store;

        if (! $store) {
            return response()->json(['message' => 'Store not found.'], Response::HTTP_NOT_FOUND);
        }

        if (! $store->address) {
            return (new AddressResource(null))
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        }

        return AddressResource::make($store->loadMissing('address')?->address)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
