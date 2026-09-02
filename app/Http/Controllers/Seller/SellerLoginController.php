<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerLoginRequest;
use App\Http\Resources\Seller\SellerResource;
use App\Mail\SellerOTPMail;
use App\Models\Seller\Seller;
use App\Services\AuthFailureNotifier;
use App\Services\OTPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class SellerLoginController extends Controller
{
    public function __invoke(SellerLoginRequest $request, AuthFailureNotifier $notifier): JsonResponse
    {
        try {
            $seller = Seller::query()->where('email', $request->validated('email'))->first();

            if (! $seller || ! Hash::check($request->validated('password'), $seller->password)) {
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
            $notifier->notify('login', (string) $request->input('email'), $e);

            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
