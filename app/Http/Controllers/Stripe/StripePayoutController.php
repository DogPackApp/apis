<?php

namespace App\Http\Controllers\Stripe;

use App\Http\Controllers\Controller;
use App\Models\Store\Store;
use App\Services\StripeKeyService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;

class StripePayoutController extends Controller
{
    public function __invoke(Request $request, Store $store): JsonResponse
    {
        try {
            DB::beginTransaction();
            $store->refresh()->loadMissing(['country'])?->country;

            $stripeKey = StripeKeyService::key($store->country_id);

            $stripe = new StripeClient([
                'api_key' => $stripeKey,
            ]);

            if ($store->stripe_status !== 'verified') {
                return response()->json([
                    'error' => 'You stripe account is not yet activated.',
                ], Response::HTTP_OK);
            }

            $loginLink = $stripe->accounts->createLoginLink($store->stripe_account_id);

            return response()->json([
                'url' => $loginLink->url,
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            DB::rollBack();

            $message = app()->environment() !== 'production'
                ? $e->getMessage()
                : 'Something went wrong';

            return response()->json([
                'errors' => ['message' => $message],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
