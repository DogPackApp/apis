<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Services\StripeKeyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeUpdateDefaultCardController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'payment_method' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors(), 422);
            }

            $store = auth()->user()->store;
            $stripe = new StripeClient(StripeKeyService::key($store->country_id));

            // Ensure the store has a Stripe customer ID
            if (! $store->stripe_customer_id) {
                return $this->errorResponse('You haven\'t subscribe to any plan yet!', Response::HTTP_BAD_REQUEST);
            }

            // Set the new default payment method
            $stripe->customers->update($store->stripe_customer_id, [
                'invoice_settings' => [
                    'default_payment_method' => $request->payment_method,
                ],
            ]);

            $paymentMethod = $stripe->paymentMethods->retrieve($request->payment_method);

            // Update store info
            $store->update([
                'pm_type' => $paymentMethod->card->brand,
                'pm_last_four' => $paymentMethod->card->last4,
            ]);

            return $this->successResponse('Default card updated');

        } catch (ApiErrorException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->errorResponse(app()->isProduction() ? 'Something went wrong.' : $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
