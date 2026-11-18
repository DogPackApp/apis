<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\StoreResource;
use App\Models\Store\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class StoreShowController extends Controller
{
    #[OA\Get(
        path: '/api/marketplace/store',
        summary: "Get the authenticated seller's store",
        security: [['sellerAuth' => []]],
        tags: ['Store'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Store',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Store')])
            ),
            new OA\Response(
                response: 404,
                description: 'The seller has not created a store yet',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'No Store Found')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $store = Store::query()
            ->with(['country', 'language', 'currency', 'storeInfo', 'settings'])
            ->where('seller_id', auth()->id())
            ->first();

        if (! $store) {
            return response()->json(['message' => 'No Store Found'], Response::HTTP_NOT_FOUND);
        }

        return StoreResource::make($store)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
