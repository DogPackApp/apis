<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerChangePasswordRequest;
use App\Services\AuthFailureNotifier;
use App\Services\SellerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerChangePasswordController extends Controller
{
    #[OA\Post(
        path: '/api/seller/password/change',
        summary: "Change the authenticated seller's password",
        description: 'Step 2 of the authenticated "change password" flow — pass the token from POST /api/seller/password/current. Sends the same SellerPasswordUpdatedMail confirmation as the public reset flow.',
        security: [['sellerAuth' => []]],
        tags: ['Seller Security'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password changed',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Your password has been reset!')])
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid or expired token',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string')])
            ),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(SellerChangePasswordRequest $request, SellerService $sellerService, AuthFailureNotifier $notifier): JsonResponse
    {
        try {
            $status = $sellerService->resetPassword([
                'email' => $request->user()->email,
                'token' => $request->validated('token'),
                'password' => $request->validated('password'),
            ]);
        } catch (\Throwable $e) {
            report($e);
            $notifier->notify('change_password', (string) $request->user()?->email, $e);

            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($status == Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], Response::HTTP_OK);
        }

        return response()->json(['message' => __($status)], Response::HTTP_BAD_REQUEST);
    }
}
