<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerForgotPasswordRequest;
use App\Services\AuthFailureNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

class SellerForgotPasswordController extends Controller
{
    public function __invoke(SellerForgotPasswordRequest $request, AuthFailureNotifier $notifier): JsonResponse
    {
        try {
            $status = Password::broker('sellers')->sendResetLink(
                $request->validated()
            );
        } catch (\Throwable $e) {
            report($e);
            $notifier->notify('forgot_password', (string) $request->input('email'), $e);

            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($status == Password::RESET_LINK_SENT) {
            return response()->json(['message' => 'Reset link sent to your email.'], Response::HTTP_OK);
        }

        return response()->json(['message' => 'Unable to send reset link.'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
