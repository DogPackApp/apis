<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Models\Store\Store;
use App\Services\StripeKeyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Stripe\StripeClient;

class StripeRetryPaymentController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'card_id' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), 422);
            }

            DB::beginTransaction();

            $validated = $validator->validated();
            $user = auth()->user();

            $store = Store::with('storesubscription')->where('seller_id', $user->id)->first();
            if (! $store) {
                return $this->errorResponse('Store not found', Response::HTTP_NOT_FOUND);
            }

            $currentSub = $store->storesubscription;
            if (! $currentSub) {
                return $this->errorResponse('Subscription not found', Response::HTTP_NOT_FOUND);
            }

            // pull latest invoice from a stripe subscription
            $stripe = new StripeClient(StripeKeyService::key($store->country_id));
            $subscription = $stripe->subscriptions->retrieve($currentSub->stripe_subscription_id, [
                'expand' => ['latest_invoice.payment_intent'],
            ]);
            $invoice = $subscription->latest_invoice;

            if ($invoice->status !== 'open' || ! $invoice->attempted) {
                // TODO: Also ensure that our DB reflects the correct status of the invoice
                return $this->errorResponse('This invoice is not eligible for retry. It has either already been paid or does not have a failed payment attempt.', Response::HTTP_BAD_REQUEST);
            }

            $params = [];

            // i) Check if user wants to change card for retry
            if (isset($validated['card_id']) && ! empty($validated['card_id'])) {
                $cardId = $validated['card_id'];
                //
                // 1) Attach & set default card (on both customer & subscription)
                //
                // Retrieve existing payment method from Stripe
                $paymentMethod = $stripe->paymentMethods->retrieve($cardId);
                $params['payment_method'] = $paymentMethod->id;

                // If it's not already attached to this customer, attach it
                if ($paymentMethod->customer !== $store->stripe_customer_id) {
                    $stripe->paymentMethods->attach($cardId, [
                        'customer' => $store->stripe_customer_id,
                    ]);
                }

                // 1a) Set as default for the subscription
                $stripe->subscriptions->update(
                    $currentSub->stripe_subscription_id,
                    ['default_payment_method' => $cardId]
                );

                // 1b) Also set it as default for all future invoices on the customer
                $stripe->customers->update($store->stripe_customer_id, [
                    'invoice_settings' => [
                        'default_payment_method' => $cardId,
                    ],
                ]);

                // 1c) Update local store record with card brand + last4 (if it's a card object)
                if (isset($paymentMethod->card)) {
                    $store->pm_type = $paymentMethod->card->brand;
                    $store->pm_last_four = $paymentMethod->card->last4;
                    $store->save();
                }
            }

            // ii) Retry the invoice
            // PS: This will also throw error if its not a valid retriable invoice
            $stripe->invoices->pay($invoice->id, $params);
            DB::commit();

            return $this->successResponse('Payment retried successfully');

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(app()->isProduction() ? 'Something went wrong.' : $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
