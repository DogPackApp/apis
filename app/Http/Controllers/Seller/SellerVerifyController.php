<?php

namespace App\Http\Controllers\Seller;

use App\Exceptions\SellerIncorrectOTPException;
use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use App\Services\OTPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SellerVerifyController extends Controller
{
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
