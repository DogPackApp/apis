<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Store\Store;
use App\Services\StripeConnectService;
use App\Services\StripeKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class StoreStripeConnectController extends Controller
{
    #[OA\Post(
        path: '/api/seller/stripe/connect',
        summary: "Start or resume Stripe Connect onboarding for the seller's store",
        description: 'Creates a Stripe Express account for the store (or refreshes the onboarding link if one already exists) and returns the URL to send the seller to.',
        security: [['sellerAuth' => []]],
        tags: ['Store'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Onboarding URL',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'url', type: 'string', format: 'uri')])
            ),
            new OA\Response(
                response: 404,
                description: 'The seller has no store yet',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Store not found.')])
            ),
            new OA\Response(
                response: 422,
                description: 'Stripe request failed',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'errors', type: 'object', example: ['message' => 'Something went wrong'])])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(Request $request, StripeConnectService $stripeConnect): JsonResponse
    {
        $store = $request->user()->store;

        if (! $store) {
            return response()->json(['message' => 'Store not found.'], Response::HTTP_NOT_FOUND);
        }

        $store->loadMissing('country');
        $stripeKey = StripeKeyService::key($store->country_id);

        try {
            if ($store->stripe_account_id && in_array($store->stripe_status, [Store::STRIPE_STATUS_PENDING, Store::STRIPE_STATUS_VERIFIED], true)) {
                $url = $stripeConnect->createAccountLink($store->stripe_account_id, $stripeKey);

                return response()->json(['url' => $url], Response::HTTP_OK);
            }

            $url = DB::transaction(function () use ($store, $stripeConnect, $stripeKey) {
                $configKey = StripeKeyService::configKey($store->country_id);
                $countryShortName = $store->country?->short_name ?? 'US';

                $isSameRegion = ($configKey === StripeKeyService::DEFAULT_CONFIG && $countryShortName === 'US')
                    || ($configKey === 'marketplace_stripe_uk' && $countryShortName === 'GB');

                $serviceAgreement = $isSameRegion ? 'full' : 'recipient';

                $accountId = $stripeConnect->createExpressAccount($countryShortName, $serviceAgreement, $stripeKey);
                $url = $stripeConnect->createAccountLink($accountId, $stripeKey);

                $store->stripe_account_id = $accountId;
                $store->stripe_status = Store::STRIPE_STATUS_PENDING;
                $store->save();

                return $url;
            });

            return response()->json(['url' => $url], Response::HTTP_OK);
        } catch (\Throwable $e) {
            report($e);

            $message = app()->isProduction() ? 'Something went wrong' : $e->getMessage();

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
