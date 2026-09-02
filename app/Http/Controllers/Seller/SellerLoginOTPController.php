<?php

namespace App\Http\Controllers\Seller;

use App\Exceptions\SellerIncorrectOTPException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerLoginOtpRequest;
use App\Http\Resources\Seller\SellerResource;
use App\Models\Seller\Seller;
use App\Services\AuthFailureNotifier;
use App\Services\OTPService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SellerLoginOTPController extends Controller
{
    public function __invoke(SellerLoginOtpRequest $request, AuthFailureNotifier $notifier): JsonResponse
    {
        $seller = Seller::query()->where('email', $request->validated('email'))->first();

        if (! $seller) {
            return response()->json(['message' => 'Invalid or expired OTP.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            (new OTPService($seller))->validate($request->validated('otp'));
        } catch (SellerIncorrectOTPException $exception) {
            return response()->json(['message' => 'Invalid or expired OTP.'], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            report($e);
            $notifier->notify('login_otp', (string) $request->input('email'), $e);

            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $token = $seller->createToken('SellerAuthToken', ['seller'])->accessToken;

        return SellerResource::make($seller)
            ->additional(['token' => $token])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
