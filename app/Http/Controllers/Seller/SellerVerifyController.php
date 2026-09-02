<?php

namespace App\Http\Controllers\Seller;

use App\Exceptions\SellerIncorrectOTPException;
use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use App\Services\OTPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerVerifyController extends Controller
{
    #[OA\Post(
        path: '/api/seller/verify',
        summary: 'Verify the registration OTP',
        description: 'Sets the seller status to verified and starts onboarding (creates the OnboardingStatus row with every step at 0).',
        security: [['sellerAuth' => []]],
        tags: ['Seller Auth'],
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
                description: 'Verified',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'OTP verified successfully.')])
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
    public function __invoke(Request $request, OnboardingService $onboardingService): JsonResponse
    {
        $request->validate([
            'otp' => 'required|numeric',
        ]);

        $seller = $request->user();

        try {
            (new OTPService($seller))->validate($request->input('otp'));
        } catch (SellerIncorrectOTPException $exception) {
            return response()->json(['message' => 'Invalid or expired OTP.'], Response::HTTP_BAD_REQUEST);
        }

        $seller->update(['status' => 1]);

        $onboardingService->initiateOnboarding($seller);

        return response()->json(['message' => 'OTP verified successfully.'], Response::HTTP_OK);
    }
}
