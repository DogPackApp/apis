<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerRegisterRequest;
use App\Http\Resources\Seller\SellerResource;
use App\Mail\SellerOTPMail;
use App\Models\Seller\Seller;
use App\Services\AuthFailureNotifier;
use App\Services\OTPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class SellerRegistrationController extends Controller
{
    public function __invoke(SellerRegisterRequest $request, AuthFailureNotifier $notifier): JsonResponse
    {
        try {
            $seller = new Seller;
            $seller->first_name = $request->validated('first_name');
            $seller->last_name = $request->validated('last_name');
            $seller->email = $request->validated('email');
            $seller->password = Hash::make($request->validated('password'));
            $seller->status = 0;
            $seller->twofa_enabled = 0;
            $seller->save();

            $token = $seller->createToken('SellerAuthToken', ['seller'])->accessToken;

            $otpService = (new OTPService($seller))
                ->invalidate()
                ->generate();

            Mail::to($seller->email)->send(new SellerOTPMail($seller, $otpService->otp));

            return SellerResource::make($seller)
                ->additional(['token' => $token])
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            report($e);
            $notifier->notify('register', (string) $request->input('email'), $e);

            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
