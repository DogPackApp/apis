<?php

namespace App\Http\Controllers\Seller;

use App\Exceptions\SellerIncorrectOTPException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerTwoFactorEnableRequest;
use App\Mail\SellerTwoFactorEnabledMail;
use App\Services\AuthFailureNotifier;
use App\Services\OTPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerTwoFactorEnableController extends Controller
{
    #[OA\Post(
        path: '/api/seller/2fa/enable',
        summary: 'Enable two-factor authentication',
        description: 'Verifies the OTP sent by POST /api/seller/2fa/otp, sets twofa_enabled=1, and sends a confirmation email.',
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
                description: '2FA enabled',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Two-factor authentication enabled.')])
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
    public function __invoke(SellerTwoFactorEnableRequest $request, AuthFailureNotifier $notifier): JsonResponse
    {
        $seller = $request->user();

        try {
            (new OTPService($seller))->validate($request->validated('otp'));
        } catch (SellerIncorrectOTPException $exception) {
            return response()->json(['message' => 'Invalid or expired OTP.'], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            report($e);
            $notifier->notify('2fa_enable', (string) $seller->email, $e);

            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $seller->update(['twofa_enabled' => 1]);

        Mail::to($seller->email)->send(new SellerTwoFactorEnabledMail($seller));

        return response()->json(['message' => 'Two-factor authentication enabled.'], Response::HTTP_OK);
    }
}
