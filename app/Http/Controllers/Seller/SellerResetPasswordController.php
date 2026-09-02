<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerResetPasswordRequest;
use App\Services\AuthFailureNotifier;
use App\Services\SellerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

class SellerResetPasswordController extends Controller
{
    public function __invoke(SellerResetPasswordRequest $request, SellerService $sellerService, AuthFailureNotifier $notifier): JsonResponse
    {
        try {
            $status = $sellerService->resetPassword($request->validated());
        } catch (\Throwable $e) {
            report($e);
            $notifier->notify('reset_password', (string) $request->input('email'), $e);

            return response()->json(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($status == Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)], Response::HTTP_OK);
        }

        return response()->json(['message' => __($status)], Response::HTTP_BAD_REQUEST);
    }
}
