<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\Subscribe\CardResource;
use App\Services\StripeKeyService;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;

class StripeStoreCardController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke()
    {

        try {

            $store = auth()->user()->store;
            $stripe = new StripeClient(StripeKeyService::key($store->country_id));

            // Ensure the store has a Stripe customer ID
            if (! $store->stripe_customer_id) {
                return $this->errorResponse('You haven\'t subscribe to any plan yet!', Response::HTTP_BAD_REQUEST);
            }
            // Attach the new payment method to the customer
            $paymentMethods = $stripe->paymentMethods->all(['customer' => $store->stripe_customer_id, 'type' => 'card']);

            $customer = $stripe->customers->retrieve($store->stripe_customer_id);
            $defaultPaymentMethodId = $customer->invoice_settings->default_payment_method;

            // Map resources with default ID
            $cards = collect($paymentMethods->data)->map(function ($paymentMethod) use ($defaultPaymentMethodId) {
                return (new CardResource($paymentMethod, $defaultPaymentMethodId))->toArray(request());
            });

            return response()->json([
                'data' => $cards,
            ]);

        } catch (ApiErrorException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->errorResponse(app()->isProduction() ? 'Something went wrong.' : $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

    }
}
