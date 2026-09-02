<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

class SellerForgotPasswordController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email', 'exists:sellers,email']]);

        $status = Password::broker('sellers')->sendResetLink(
            $request->only('email')
        );

        if ($status == Password::RESET_LINK_SENT) {
            return response()->json(['message' => 'Reset link sent to your email.'], Response::HTTP_OK);
        }

        return response()->json(['message' => 'Unable to send reset link.'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
