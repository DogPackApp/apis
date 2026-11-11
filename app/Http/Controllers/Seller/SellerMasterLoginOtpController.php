<?php

namespace App\Http\Controllers\Seller;

use App\Exceptions\SellerIncorrectOTPException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Seller\SellerResource;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use App\Services\OTPService;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SellerMasterLoginOtpController extends Controller
{
    use ValidatesRequests;

    /**
     * @throws SellerIncorrectOTPException
     */
    public function __invoke(Request $request): JsonResponse
    {
        $input = $this->validate($request, [
            'store_id' => ['required', 'string', 'exists:stores,uuid'],
            'otp' => ['required', 'numeric'],
        ]);

        $store = Store::where([
            'uuid' => $input['store_id'],
        ])->firstOrFail();

        $seller = Seller::findOrFail($store->seller_id);

        (new OTPService($seller, 'master-login'))->validate($input['otp']);

        $token = $seller->createToken(
            'SellerMasterLoginToken', ['master-login']
        )->accessToken;

        return SellerResource::make($seller)
            ->additional([
                'token' => $token,
                'is_master_login' => true,
            ])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
