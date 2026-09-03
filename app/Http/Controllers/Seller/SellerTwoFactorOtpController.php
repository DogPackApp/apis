<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Mail\SellerOTPMail;
use App\Services\AuthFailureNotifier;
use App\Services\OTPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerTwoFactorOtpController extends Controller
{
    #[OA\Post(
        path: '/api/seller/2fa/otp',
        summary: 'Send an OTP to start enabling two-factor authentication',
        description: 'Emails an OTP to the authenticated seller. Submit it to POST /api/seller/2fa/enable to actually turn 2FA on.',
        security: [['sellerAuth' => []]],
        tags: ['Seller Security'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OTP emailed',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'OTP sent to your email.')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(Request $request, AuthFailureNotifier $notifier): JsonResponse
    {
        try {
            $seller = $request->user();

            $otpService = (new OTPService($seller))
                ->invalidate()
                ->generate();

            Mail::to($seller->email)->send(new SellerOTPMail($seller, $otpService->otp));

            return response()->json(['message' => 'OTP sent to your email.'], Response::HTTP_OK);
        } catch (\Throwable $e) {
            report($e);
            $notifier->notify('2fa_otp', (string) $request->user()?->email, $e);

            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
