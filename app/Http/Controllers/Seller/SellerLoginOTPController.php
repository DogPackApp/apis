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
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerLoginOTPController extends Controller
{
    #[OA\Post(
        path: '/api/seller/login/otp',
        summary: 'Complete a 2FA login by submitting the emailed OTP',
        tags: ['Seller Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'otp'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'otp', type: 'integer', example: 1234),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'OTP valid, token issued',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Seller'),
                        new OA\Property(property: 'token', type: 'string'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Unknown email or invalid/expired OTP',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Invalid or expired OTP.')])
            ),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 429, description: 'Too many attempts — throttled'),
            new OA\Response(response: 500, description: 'Internal server error'),
        ]
    )]
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
