<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerLoginRequest;
use App\Http\Resources\Seller\SellerResource;
use App\Mail\SellerOTPMail;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use App\Services\AuthFailureNotifier;
use App\Services\OTPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerLoginController extends Controller
{
    #[OA\Post(
        path: '/api/seller/login',
        summary: 'Log a seller in with email and password',
        description: 'Returns an access token immediately, unless the seller has 2FA enabled — in that case an OTP is emailed and a 202 is returned instead, and the token is issued by POST /api/seller/login/otp.',
        tags: ['Seller Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'seller@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful, token issued (2FA disabled)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Seller'),
                        new OA\Property(property: 'token', type: 'string', example: '1|abcdef123456...'),
                    ]
                )
            ),
            new OA\Response(
                response: 202,
                description: '2FA is enabled — OTP emailed, no token yet',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'OTP sent to your email.')]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials, or the seller\'s store is inactive/blocked',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'message', type: 'string', example: 'Invalid credentials')]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'errors', type: 'object')]
                )
            ),
            new OA\Response(
                response: 429,
                description: 'Too many attempts — throttled (10/min per email or IP)'
            ),
        ]
    )]
    public function __invoke(SellerLoginRequest $request, AuthFailureNotifier $notifier): JsonResponse
    {
        try {
            $seller = Seller::query()->where('email', $request->validated('email'))->first();

            if (! $seller || ! Hash::check($request->validated('password'), $seller->password)) {
                return response()->json(['message' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
            }

            $seller->load('store');

            if ($seller->store && $seller->store->states === Store::STATES_INACTIVE) {
                return response()->json([
                    'message' => 'Your account is blocked, please contact '.config('services.DOGPACK_EMAIL').'.',
                ], Response::HTTP_UNAUTHORIZED);
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
