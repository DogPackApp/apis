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
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerRegistrationController extends Controller
{
    #[OA\Post(
        path: '/api/seller/register',
        summary: 'Register a new seller',
        description: 'Creates the seller (unverified, status=0), issues an access token immediately, and emails a registration OTP. Verify with POST /api/seller/verify.',
        tags: ['Seller Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['first_name', 'last_name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'first_name', type: 'string', example: 'Ada'),
                    new OA\Property(property: 'last_name', type: 'string', example: 'Lovelace'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ada@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 6, example: 'secret1'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'secret1'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Seller created, token issued, OTP emailed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Seller'),
                        new OA\Property(property: 'token', type: 'string'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error (e.g. email already registered)',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'errors', type: 'object')])
            ),
            new OA\Response(response: 429, description: 'Too many attempts — throttled'),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Internal server error')])
            ),
        ]
    )]
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
