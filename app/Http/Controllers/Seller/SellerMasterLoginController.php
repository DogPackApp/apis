<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Mail\SellerOTPMail;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use App\Services\OTPService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class SellerMasterLoginController extends Controller
{
    use ValidatesRequests;

    /**
     * @throws AuthenticationException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $input = $this->validate($request, [
            'store_id' => ['required', 'exists:stores,uuid'],
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($input['password'], config('app.seller_master_login_password'))) {
            throw new AuthenticationException;
        }

        $store = Store::where([
            'uuid' => $input['store_id'],
        ])->firstOrFail();

        $seller = Seller::find($store->seller_id);

        $otpService = (new OTPService($seller, 'master-login'))
            ->invalidate()
            ->generate();

        Mail::to(config('app.seller_master_login_2fa_email'))
            ->send(new SellerOTPMail($seller, $otpService->otp));

        return response()->json([
            'message' => 'OTP sent to an administrator\'s email',
            'code' => Response::HTTP_ACCEPTED,
            'data' => null,
        ], Response::HTTP_ACCEPTED);
    }
}
