<?php

namespace App\Http\Controllers\Seller;

use App\Exceptions\SellerIncorrectOTPException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerTwoFactorDisableRequest;
use App\Services\AuthFailureNotifier;
use App\Services\OTPService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerTwoFactorDisableController extends Controller
{
    #[OA\Post(
        path: '/api/seller/2fa/disable',
        summary: 'Disable two-factor authentication',
        description: 'Verifies the OTP sent by POST /api/seller/2fa/otp and sets twofa_enabled=0.',
        security: [['sellerAuth' => []]],
        tags: ['Seller Security'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['otp'],
                properties: [new OA\Property(property: 'otp', type: 'integer', example: 1234)]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: '2FA disabled',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Two-factor authentication disabled.')])
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid or expired OTP',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Invalid or expired OTP.')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function __invoke(SellerTwoFactorDisableRequest $request, AuthFailureNotifier $notifier): JsonResponse
    {
        $seller = $request->user();

        try {
            (new OTPService($seller))->validate($request->validated('otp'));
        } catch (SellerIncorrectOTPException $exception) {
            return response()->json(['message' => 'Invalid or expired OTP.'], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            report($e);
            $notifier->notify('2fa_disable', (string) $seller->email, $e);

            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $seller->update(['twofa_enabled' => 0]);

        return response()->json(['message' => 'Two-factor authentication disabled.'], Response::HTTP_OK);
    }
}
