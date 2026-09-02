<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerForgotPasswordRequest;
use App\Services\AuthFailureNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerForgotPasswordController extends Controller
{
    #[OA\Post(
        path: '/api/seller/password/forgot',
        summary: 'Request a password reset link',
        description: 'Sends a reset link email via the sellers password broker (60-minute token expiry).',
        tags: ['Seller Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [new OA\Property(property: 'email', type: 'string', format: 'email')]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reset link sent',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Reset link sent to your email.')])
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error (email missing/not registered) or broker was unable to send the link',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Unable to send reset link.')])
            ),
            new OA\Response(response: 429, description: 'Too many attempts — throttled'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
    public function __invoke(SellerForgotPasswordRequest $request, AuthFailureNotifier $notifier): JsonResponse
    {
        try {
            $status = Password::broker('sellers')->sendResetLink(
                $request->validated()
            );
        } catch (\Throwable $e) {
            report($e);
            $notifier->notify('forgot_password', (string) $request->input('email'), $e);

            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($status == Password::RESET_LINK_SENT) {
            return response()->json(['message' => 'Reset link sent to your email.'], Response::HTTP_OK);
        }

        return response()->json(['message' => 'Unable to send reset link.'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
