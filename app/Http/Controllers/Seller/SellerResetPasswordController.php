<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerResetPasswordRequest;
use App\Services\AuthFailureNotifier;
use App\Services\SellerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerResetPasswordController extends Controller
{
    #[OA\Post(
        path: '/api/seller/password/reset',
        summary: 'Reset a password using a reset-link token',
        description: 'On success, also sends a SellerPasswordUpdatedMail confirmation email.',
        tags: ['Seller Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password reset',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Your password has been reset!')])
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid or expired token',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'This password reset token is invalid.')])
            ),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 429, description: 'Too many attempts — throttled'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function __invoke(SellerResetPasswordRequest $request, SellerService $sellerService, AuthFailureNotifier $notifier): JsonResponse
    {
        try {
            $status = $sellerService->resetPassword($request->validated());
        } catch (\Throwable $e) {
            report($e);
            $notifier->notify('reset_password', (string) $request->input('email'), $e);

            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($status == Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], Response::HTTP_OK);
        }

        return response()->json(['message' => __($status)], Response::HTTP_BAD_REQUEST);
    }
}
