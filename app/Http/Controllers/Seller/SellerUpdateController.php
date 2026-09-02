<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerUpdateRequest;
use App\Http\Resources\Seller\SellerResource;
use App\Models\Seller\Seller;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerUpdateController extends Controller
{
    #[OA\Put(
        path: '/api/seller/{seller}',
        summary: "Update the authenticated seller's own profile",
        description: 'A seller may only update themself — updating any other seller uuid returns 422.',
        security: [['sellerAuth' => []]],
        tags: ['Seller Profile'],
        parameters: [
            new OA\Parameter(name: 'seller', in: 'path', required: true, description: 'Seller uuid', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'first_name', type: 'string'),
                    new OA\Property(property: 'last_name', type: 'string'),
                    new OA\Property(property: 'phone', type: 'string', minLength: 10),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Updated',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Seller')])
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error, or attempting to update a seller other than yourself',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'errors', type: 'object', example: ['others' => 'Action not allowed.'])]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(SellerUpdateRequest $request, Seller $seller): JsonResponse
    {
        if ($seller->id !== $request->user()->id) {
            return response()->json(['errors' => [
                'others' => 'Action not allowed.',
            ]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $seller->fill($request->safe()->only(['first_name', 'last_name', 'phone']));
        $seller->save();

        return SellerResource::make($seller)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
