<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerGoogleLoginRequest;
use App\Http\Resources\Seller\SellerResource;
use App\Mail\SellerOTPMail;
use App\Models\Seller\Seller;
use App\Services\AuthFailureNotifier;
use App\Services\OnboardingService;
use App\Services\OTPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerGoogleLoginController extends Controller
{
    #[OA\Post(
        path: '/api/seller/google/login',
        summary: 'Log in or register a seller via Google OAuth code exchange',
        description: 'Exchanges an authorization `code` (obtained by the frontend via Google\'s popup/postMessage flow) for a token, then either logs in an existing seller (linking google_id if unset) or creates a new, pre-verified seller.',
        tags: ['Seller Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [new OA\Property(property: 'code', type: 'string')]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login/registration successful, token issued (2FA disabled)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Seller'),
                        new OA\Property(property: 'token', type: 'string'),
                    ]
                )
            ),
            new OA\Response(
                response: 202,
                description: '2FA is enabled on the linked seller — OTP emailed, no token yet',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'OTP sent to your email.'),
                        new OA\Property(property: 'email', type: 'string', format: 'email'),
                        new OA\Property(property: 'twoFa', type: 'boolean', example: true),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Google email is not verified',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Email not verified with Google')])
            ),
            new OA\Response(response: 422, description: 'Validation error (code missing)'),
            new OA\Response(
                response: 500,
                description: 'Google code exchange failed or another unexpected error',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Authentication failed')])
            ),
        ]
    )]
    public function __invoke(SellerGoogleLoginRequest $request, AuthFailureNotifier $notifier): JsonResponse
    {
        try {
            $googleUser = $this->verifyGoogleCode($request->validated('code'));

            if (! isset($googleUser['email']) || ! isset($googleUser['verified_email']) || ! $googleUser['verified_email']) {
                return response()->json(['message' => 'Email not verified with Google'], Response::HTTP_BAD_REQUEST);
            }

            $seller = Seller::query()
                ->where('email', $googleUser['email'])
                ->orWhere('google_id', $googleUser['id'])
                ->first();

            if ($seller) {
                if (empty($seller->google_id) && isset($googleUser['id'])) {
                    $seller->google_id = $googleUser['id'];
                    $seller->save();
                }

                if ($seller->is2FAEnabled()) {
                    $otpService = (new OTPService($seller))
                        ->invalidate()
                        ->generate();

                    Mail::to($seller->email)->send(new SellerOTPMail($seller, $otpService->otp));

                    return response()->json([
                        'message' => 'OTP sent to your email.',
                        'email' => $seller->email,
                        'twoFa' => true,
                    ], Response::HTTP_ACCEPTED);
                }
            } else {
                $seller = $this->createSellerFromGoogle($googleUser);
            }

            $token = $seller->createToken('SellerAuthToken', ['seller'])->accessToken;

            return SellerResource::make($seller)
                ->additional(['token' => $token])
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        } catch (\Throwable $e) {
            Log::error('Google OAuth error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $notifier->notify('google_login', 'unknown', $e);

            return response()->json(['message' => 'Authentication failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyGoogleCode(string $code): array
    {
        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => 'postmessage',
            'grant_type' => 'authorization_code',
        ]);

        if (! $tokenResponse->successful()) {
            throw new \Exception('Failed to exchange code for token');
        }

        $accessToken = $tokenResponse->json('access_token');

        $userResponse = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
        ])->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if (! $userResponse->successful()) {
            throw new \Exception('Failed to get user information');
        }

        return $userResponse->json();
    }

    /**
     * @param  array<string, mixed>  $googleUser
     */
    private function createSellerFromGoogle(array $googleUser): Seller
    {
        $firstName = $googleUser['given_name'] ?? '';
        $lastName = $googleUser['family_name'] ?? '';

        if (empty($firstName) && ! empty($googleUser['name'])) {
            $nameParts = explode(' ', $googleUser['name'], 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
        }

        if (empty($firstName)) {
            $firstName = 'Google';
        }
        if (empty($lastName)) {
            $lastName = 'User';
        }

        $seller = new Seller;
        $seller->first_name = $firstName;
        $seller->last_name = $lastName;
        $seller->email = $googleUser['email'];
        $seller->google_id = $googleUser['id'] ?? null;
        $seller->status = 1;
        $seller->twofa_enabled = 0;
        $seller->password = Hash::make(Str::random(10));
        $seller->save();

        (new OnboardingService)->initiateOnboarding($seller);

        return $seller;
    }
}
