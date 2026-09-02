<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Seller\SellerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerProfileController extends Controller
{
    #[OA\Get(
        path: '/api/seller/me',
        summary: "Get the authenticated seller's profile",
        description: 'Eager-loads the store relation, so `data.store` is included (null if the seller has not created a store yet).',
        security: [['sellerAuth' => []]],
        tags: ['Seller Profile'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Seller profile',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Seller')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        return SellerResource::make($request->user()->load('store'))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
