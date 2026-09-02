<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreUpdateRequest;
use App\Http\Resources\Store\StoreResource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class StoreUpdateController extends Controller
{
    #[OA\Put(
        path: '/api/seller/store',
        summary: "Update the authenticated seller's store",
        description: 'Partial update — every field is optional.',
        security: [['sellerAuth' => []]],
        tags: ['Store'],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'image', type: 'string', nullable: true),
                    new OA\Property(property: 'cover_image', type: 'string', nullable: true),
                    new OA\Property(property: 'social_links', type: 'object', nullable: true),
                    new OA\Property(property: 'timezone', type: 'string', nullable: true, example: 'America/New_York'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Updated',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Store')])
            ),
            new OA\Response(
                response: 404,
                description: 'The seller has no store yet',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Store not found.')])
            ),
            new OA\Response(response: 422, description: 'Validation error (e.g. invalid timezone identifier, name already taken)'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
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
