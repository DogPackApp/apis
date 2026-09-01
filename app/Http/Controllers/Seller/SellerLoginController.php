<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Seller\SellerResource;
use App\Mail\SellerOTPMail;
use App\Models\Seller\Seller;
use App\Services\OTPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class SellerLoginController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $seller = Seller::query()->where('email', $request->email)->first();

            if (! $seller || ! Hash::check($request->password, $seller->password)) {
                return response()->json(['message' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
            }

            if ($seller->is2FAEnabled()) {
                $otpService = (new OTPService($seller))
                    ->invalidate()
                    ->generate();

                Mail::to($seller->email)->send(new SellerOTPMail($seller, $otpService->otp));

                return response()->json(['message' => 'OTP sent to your email.'], Response::HTTP_ACCEPTED);
            }

            $token = $seller->createToken('SellerAuthToken', ['seller'])->accessToken;

            return SellerResource::make($seller)
                ->additional(['token' => $token])
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
