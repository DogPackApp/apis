<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerCurrentPasswordRequest;
use App\Services\AuthFailureNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerCurrentPasswordController extends Controller
{
    #[OA\Post(
        path: '/api/seller/password/current',
        summary: 'Verify the current password and get a password-reset token',
        description: 'Step 1 of the authenticated "change password" flow. Confirms the seller knows their current password and mints a token via the same sellers password broker used by the public forgot-password flow. Pass the returned token to POST /api/seller/password/change.',
        security: [['sellerAuth' => []]],
        tags: ['Seller Security'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [new OA\Property(property: 'password', type: 'string', format: 'password')]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password confirmed, token issued',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'token', type: 'string')])
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error, or the provided password is incorrect',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'errors', type: 'object', example: ['password' => 'The provided password is incorrect.'])])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(SellerCurrentPasswordRequest $request, AuthFailureNotifier $notifier): JsonResponse
    {
        try {
            $seller = $request->user();

            if (! Hash::check($request->validated('password'), $seller->password)) {
                return response()->json(['errors' => [
                    'password' => 'The provided password is incorrect.',
                ]], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $token = Password::broker('sellers')->createToken($seller);

            return response()->json(['token' => $token], Response::HTTP_OK);
        } catch (\Throwable $e) {
            report($e);
            $notifier->notify('current_password', (string) $request->user()?->email, $e);

            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
