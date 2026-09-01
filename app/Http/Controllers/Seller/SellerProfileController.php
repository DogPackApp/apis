<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Seller\SellerResource;
//use App\Mail\Marketplace\SellerOTPMail;
use App\Models\Seller\Seller;
//use App\Models\Marketplace\Store;
//use App\Services\Marketplace\OTPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
//use App\Notifications\SellerLoginFailed;
//use App\Notifications\SlackNotifier;
use Carbon\Carbon;

class SellerLoginController extends Controller
{
    /**
     * Handle Seller Login
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'email'    => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // [Non-production] Manual Slack test trigger
            if (config('app.env') !== 'Production' && $request->input('email') === 'dogpack+seller+panel+testing@dogpackapp.com') {
                throw new \RuntimeException('Manual Slack test triggered via test email.');
            }

            // Find seller by email
            $seller = Seller::query()->where('email', $request->email)->first();

            // Check if seller exists & password matches
            if (!$seller || !Hash::check($request->password, $seller->password)) {
                return response()->json(['message' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
            }

            //$seller->load(['store']);

//            if ($seller->store && $seller->store->states === Store::STATES_INACTIVE) {
//                return response()->json(
//                    ['message' => "You are account is blocked, please contact support@dogpackapp.com"],
//                    Response::HTTP_UNAUTHORIZED
//                );
//            }

//            if ($seller->is2FAEnabled()) {
//                $otpService = (new OTPService($seller))
//                    ->invalidate()
//                    ->generate();
//
//                Mail::to($seller->email)->send(new SellerOTPMail($seller, $otpService->otp));
//
//                return response()->json(['message' => 'OTP sent to your email.'], Response::HTTP_ACCEPTED);
//            }

            // Generate Passport token
//            $start = microtime(true);

            $token = $seller->createToken('SellerAuthToken', ['seller'])->accessToken;

//            dd(microtime(true) - $start);
            return SellerResource::make($seller)
                ->additional(['token' => $token])
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        } catch (\Throwable $e) {
            dd($e);
            $timestamp = Carbon::now('America/Toronto')->format('Y-m-d H:i:s');

//            (new SlackNotifier())->notify(new SellerLoginFailed(
//                $request->input('email', 'unknown'),
//                $timestamp,
//                $e
//            ));

            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
