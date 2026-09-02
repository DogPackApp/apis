<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\SellerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

class SellerResetPasswordController extends Controller
{
    public function __invoke(Request $request, SellerService $sellerService): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = $sellerService->resetPassword([
            'email' => $request->email,
            'password' => $request->password,
            'token' => $request->token,
            'password_confirmation' => $request->password_confirmation,
        ]);

        if ($status == Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], Response::HTTP_OK);
        }

        return response()->json(['message' => __($status)], Response::HTTP_BAD_REQUEST);
    }
}
