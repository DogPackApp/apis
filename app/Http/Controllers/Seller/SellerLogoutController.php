<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerLogoutController extends Controller
{
    #[OA\Post(
        path: '/api/seller/logout',
        summary: 'Log the seller out',
        description: 'Revokes the current access token only — a refresh token, if any, is not revoked.',
        security: [['sellerAuth' => []]],
        tags: ['Seller Profile'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged out',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $request->user()->token()->revoke();

        return response()->json(['message' => 'Logged out successfully'], Response::HTTP_OK);
    }
}
