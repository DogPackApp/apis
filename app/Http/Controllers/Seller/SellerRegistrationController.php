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

class SellerRegistrationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:sellers,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $seller = new Seller;
        $seller->first_name = $request->input('first_name');
        $seller->last_name = $request->input('last_name');
        $seller->email = $request->input('email');
        $seller->password = Hash::make($request->input('password'));
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
    }
}
