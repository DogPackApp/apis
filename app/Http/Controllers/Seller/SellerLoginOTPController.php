<?php

namespace App\Http\Controllers\Seller;

use App\Exceptions\SellerIncorrectOTPException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Seller\SellerResource;
use App\Models\Seller\Seller;
use App\Services\OTPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class SellerLoginOTPController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'otp' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $seller = Seller::query()->where('email', $request->email)->first();

        if (! $seller) {
            return response()->json(['message' => 'Invalid or expired OTP.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            (new OTPService($seller))->validate($request->input('otp'));
        } catch (SellerIncorrectOTPException $exception) {
            return response()->json(['message' => 'Invalid or expired OTP.'], Response::HTTP_BAD_REQUEST);
        }

        $token = $seller->createToken('SellerAuthToken', ['seller'])->accessToken;

        return SellerResource::make($seller)
            ->additional(['token' => $token])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
