<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Models\Store\Store;
use App\Models\Stripe\StorePayment;
use App\Models\Stripe\StoreSubscription;
use App\Services\OnboardingService;
use App\Services\StripeKeyService;
use App\Services\Subscribe\PlanService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;

class SubscribeStripeController extends Controller
{
    use RespondsWithSellerJson;

    /**
     * Two-phase subscribe:
     *  - Phase 1 (no subscription_id): create the Stripe subscription. Normal cards
     *    subscribe immediately; SCA cards get a client_secret to complete 3DS on the FE.
     *  - Phase 2 (subscription_id present): finalize after the FE completes 3DS. Only
     *    then do we create the local store_subscriptions row and mark it active.
     *
     * stripe_customer_id / card fields are only written to DB after payment succeeds
     * (normal card path or after 3DS finalize). Unfinished 3DS must not look "complete".
     */
    public function __invoke(Request $request)
    {
        if ($request->filled('subscription_id')) {
            return $this->finalize($request);
        }

        return $this->createSubscription($request);
    }

    private function createSubscription(Request $request)
    {
        DB::beginTransaction(); // Start transaction
        try {
            // Validate required fields first
            $validator = Validator::make($request->all(), [
                'plan_id' => [
                    'required',
                    'string',
                ],
                'price_id' => [
                    'required',
                    'string',
                ],
                'address_line1' => 'required|string',
                'address_line2' => 'nullable|string',
                'city' => 'required|string',
                'state' => 'required|string',
                'postal_code' => 'required|string',
                'country_code' => 'required|string',
                'payment_method' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors(), 422);
            }

            // Validate plan_id and price_id from PlanService
            $planService = app(PlanService::class);
            $plans = $planService->getAllPlans();

            $planIdValid = false;
            $priceIdValid = false;

            foreach ($plans as $plan) {
                if (isset($plan['plan_id']) && $plan['plan_id'] === $request->plan_id) {
                    $planIdValid = true;
                    foreach ($plan['prices'] as $countryCode => $price) {
                        if (isset($price['price_id']) && $price['price_id'] === $request->price_id) {
                            $priceIdValid = true;
                            break 2; // Both valid, break out of both loops
                        }
                    }
                }
            }

            $errors = [];
            if (! $planIdValid) {
                $errors['plan_id'] = ['The selected plan_id is invalid.'];
            }
            if (! $priceIdValid) {
                $errors['price_id'] = ['The selected price_id is invalid for the given plan_id.'];
            }
            if (! empty($errors)) {
                return $this->errorResponse($errors, 422);
            }
            // Eager load subscription
            $store = Store::with('storesubscription')->where('id', auth()->user()->store->id)->first();

            // Prevent multiple subscriptions per store
            if ($store->storesubscription && $store->storesubscription->is_active) {
                return $this->errorResponse(['Store already has an active subscription. Please upgrade/downgrade instead.',
                ], Response::HTTP_CONFLICT);
            }

            $stripe = new StripeClient(StripeKeyService::key($store->country_id));

            // Resolve Stripe customer for this attempt only — do NOT save to DB until paid.
            $stripeCustomerId = $this->resolveStripeCustomerId($stripe, $store, $request);

            // Cancel any lingering incomplete Stripe subscriptions so retries do not stack up
            $this->cancelIncompleteSubscriptions($stripe, $stripeCustomerId);

            // Attach payment method (ignore if already attached to this customer)
            try {
                $stripe->paymentMethods->attach($request->payment_method, [
                    'customer' => $stripeCustomerId,
                ]);
            } catch (InvalidRequestException $e) {
                if (stripos($e->getMessage(), 'already been attached') === false) {
                    throw $e;
                }
            }

            // Set as default payment method
            $stripe->customers->update($stripeCustomerId, [
                'invoice_settings' => [
                    'default_payment_method' => $request->payment_method,
                ],
            ]);

            // Create subscription. default_incomplete lets us complete 3DS on the client
            // before the subscription becomes active.
            $subscription = $stripe->subscriptions->create([
                'customer' => $stripeCustomerId,
                'items' => [['price' => $request->price_id]],
                'default_payment_method' => $request->payment_method,
                'automatic_tax' => ['enabled' => true],
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => [
                    'save_default_payment_method' => 'on_subscription',
                ],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'plan_id' => $request->plan_id,
                    'price_id' => $request->price_id,
                    'store_id' => (string) $store->id,
                ],
            ]);

            $paymentIntent = $this->resolvePaymentIntent($stripe, $subscription);

            // Normal card: Stripe already confirmed the payment on create.
            $isPaid = in_array($subscription->status, ['active', 'trialing'], true);

            if ($isPaid) {
                $this->persistPaymentMethod($stripe, $store, $request->payment_method, $stripeCustomerId);
                $storeSub = $this->persistSubscription($store, $subscription, $request->plan_id, $request->price_id);
                $this->backfillPaymentIfNeeded($stripe, $store, $storeSub, $subscription);

                DB::commit();

                return $this->successResponse("You've successfully subscribed.");
            }

            // SCA card: hand the client_secret to the FE to complete 3DS. Do NOT write
            // stripe_customer_id / store_subscriptions / card fields yet.
            $needsClientConfirmation = $paymentIntent
                && ! empty($paymentIntent->client_secret)
                && in_array($paymentIntent->status, [
                    'requires_action',
                    'requires_confirmation',
                    'requires_payment_method',
                ], true);

            if ($needsClientConfirmation) {
                // Nothing local to persist for unfinished 3DS.
                DB::rollBack();

                return $this->successResponseWithData([
                    'requires_action' => true,
                    'payment_intent_client_secret' => $paymentIntent->client_secret,
                    'payment_intent_status' => $paymentIntent->status,
                    'subscription_id' => $subscription->id,
                    'plan_id' => $request->plan_id,
                    'price_id' => $request->price_id,
                ], 'Please verify 3D Secure to complete your subscription.');
            }

            // Payment could not proceed and there is no way for the client to continue.
            try {
                $stripe->subscriptions->cancel($subscription->id);
            } catch (ApiErrorException $e) {
                // ignore cleanup failure
            }

            DB::rollBack();

            return $this->errorResponse(
                'Unable to complete subscription payment. Please try again or use a different card.',
                Response::HTTP_BAD_REQUEST
            );

        } catch (ApiErrorException $e) {
            DB::rollBack();

            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(app()->isProduction() ? 'Something went wrong.' : $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Phase 2: finalize the subscription after the client completed 3DS.
     */
    private function finalize(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'subscription_id' => 'required|string',
                'plan_id' => 'required|string',
                'price_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors(), 422);
            }

            $store = Store::with('storesubscription')->where('id', auth()->user()->store->id)->first();

            // Webhook may activate the same subscription before this phase-2 call arrives.
            // Only block if a *different* active subscription already exists.
            $existingActive = $store->storesubscription && $store->storesubscription->is_active
                ? $store->storesubscription
                : null;
            if (
                $existingActive
                && $existingActive->stripe_subscription_id !== $request->subscription_id
            ) {
                return $this->errorResponse(
                    ['Store already has an active subscription. Please upgrade/downgrade instead.'],
                    Response::HTTP_CONFLICT
                );
            }

            $stripe = new StripeClient(StripeKeyService::key($store->country_id));
            $subscription = $stripe->subscriptions->retrieve($request->subscription_id, [
                'expand' => ['default_payment_method', 'latest_invoice.payment_intent'],
            ]);

            // Ownership: prefer metadata.store_id (customer may not be saved on store yet).
            $subscriptionStoreId = $subscription->metadata->store_id ?? null;
            $ownsByMetadata = $subscriptionStoreId !== null
                && (string) $subscriptionStoreId === (string) $store->id;
            $ownsByCustomer = $store->stripe_customer_id
                && $subscription->customer === $store->stripe_customer_id;

            if (! $ownsByMetadata && ! $ownsByCustomer) {
                DB::rollBack();

                return $this->errorResponse('Subscription does not belong to this store.', Response::HTTP_FORBIDDEN);
            }

            $isPaid = in_array($subscription->status, ['active', 'trialing'], true);
            if (! $isPaid) {
                DB::rollBack();

                return $this->errorResponse(
                    'Payment could not be completed. Please try again or use a different card.',
                    Response::HTTP_BAD_REQUEST
                );
            }

            $stripeCustomerId = is_string($subscription->customer)
                ? $subscription->customer
                : ($subscription->customer->id ?? null);

            // Persist card + stripe_customer_id only after 3DS/payment succeeded
            $paymentMethod = $subscription->default_payment_method ?? null;
            if (is_string($paymentMethod)) {
                $paymentMethod = $stripe->paymentMethods->retrieve($paymentMethod);
            }
            if ($paymentMethod && isset($paymentMethod->card)) {
                $store->pm_type = $paymentMethod->card->brand;
                $store->pm_last_four = $paymentMethod->card->last4;
            }
            if ($stripeCustomerId) {
                $store->stripe_customer_id = $stripeCustomerId;
            }
            $store->save();

            $storeSub = $this->persistSubscription($store, $subscription, $request->plan_id, $request->price_id);

            // Backfill payment if webhook arrived before this row existed
            $this->backfillPaymentIfNeeded($stripe, $store, $storeSub, $subscription);

            DB::commit();

            return $this->successResponse("You've successfully subscribed.");

        } catch (ApiErrorException $e) {
            DB::rollBack();

            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(app()->isProduction() ? 'Something went wrong.' : $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * If the webhook fired before store_subscriptions existed, the payment was not
     * recorded. Check the latest invoice and create the store_payments row if missing.
     */
    private function backfillPaymentIfNeeded(StripeClient $stripe, Store $store, StoreSubscription $storeSub, $subscription): void
    {
        try {
            $latestInvoice = $subscription->latest_invoice ?? null;
            if (is_string($latestInvoice)) {
                $latestInvoice = $stripe->invoices->retrieve($latestInvoice);
            }

            if (! $latestInvoice || $latestInvoice->status !== 'paid') {
                return;
            }

            // Already recorded by webhook — nothing to do
            $existing = StorePayment::where('stripe_invoice_id', $latestInvoice->id)->first();
            if ($existing && $existing->status === StorePayment::STATUS_PAID) {
                return;
            }

            $paidAt = ! empty($latestInvoice->status_transitions->paid_at)
                ? Carbon::createFromTimestamp($latestInvoice->status_transitions->paid_at)
                : now();

            $payment = $existing ?: new StorePayment;
            $payment->store_id = $store->id;
            $payment->stripe_invoice_id = $latestInvoice->id;
            $payment->amount = $latestInvoice->amount_paid / 100;
            $payment->currency = $latestInvoice->currency;
            $payment->status = StorePayment::STATUS_PAID;
            $payment->billing_reason = $latestInvoice->billing_reason ?? null;
            $payment->receipt_url = $latestInvoice->invoice_pdf ?? null;
            $payment->paid_at = $paidAt;
            $payment->failure_message = null;
            $payment->save();

            (new OnboardingService)->completeSubscription($store->seller);

        } catch (\Exception $e) {
            Log::warning('Payment backfill failed — webhook will handle it', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get or create a Stripe customer for this attempt without writing to the store yet.
     */
    private function resolveStripeCustomerId(StripeClient $stripe, Store $store, Request $request): string
    {
        if (! empty($store->stripe_customer_id)) {
            return $store->stripe_customer_id;
        }

        // Reuse an existing Stripe customer for this email when possible (avoid orphans on retries)
        try {
            $existing = $stripe->customers->all([
                'email' => auth()->user()->email,
                'limit' => 1,
            ]);
            if (! empty($existing->data)) {
                return $existing->data[0]->id;
            }
        } catch (ApiErrorException $e) {
            // fall through to create
        }

        $address = [
            'line1' => $request->address_line1 ?? '',
            'line2' => $request->address_line2 ?? '',
            'city' => $request->city ?? '',
            'state' => $request->state ?? '',
            'postal_code' => $request->postal_code ?? '',
            'country' => $request->country_code ?? '',
        ];
        $address = array_filter($address, fn ($v) => ! empty($v));

        $customerData = [
            'email' => auth()->user()->email,
            'name' => $store->name.' ('.auth()->user()->first_name.' '.auth()->user()->last_name.')',
            'metadata' => [
                'store_id' => (string) $store->id,
            ],
        ];

        if (! empty($address)) {
            $customerData['address'] = $address;
        }

        $customer = $stripe->customers->create($customerData);

        return $customer->id;
    }

    private function resolvePaymentIntent(StripeClient $stripe, $subscription)
    {
        $latestInvoice = $subscription->latest_invoice ?? null;
        if (is_string($latestInvoice)) {
            $latestInvoice = $stripe->invoices->retrieve($latestInvoice, [
                'expand' => ['payment_intent'],
            ]);
        }

        $paymentIntent = $latestInvoice->payment_intent ?? null;
        if (is_string($paymentIntent)) {
            $paymentIntent = $stripe->paymentIntents->retrieve($paymentIntent);
        }

        return $paymentIntent;
    }

    private function persistPaymentMethod(StripeClient $stripe, Store $store, string $paymentMethodId, string $stripeCustomerId): void
    {
        $store->stripe_customer_id = $stripeCustomerId;

        $paymentMethod = $stripe->paymentMethods->retrieve($paymentMethodId);
        if ($paymentMethod && isset($paymentMethod->card)) {
            $store->pm_type = $paymentMethod->card->brand;
            $store->pm_last_four = $paymentMethod->card->last4;
        }

        $store->save();
    }

    private function persistSubscription(Store $store, $subscription, string $planId, string $priceId): StoreSubscription
    {
        // Upsert by Stripe subscription id so phase-2 + webhook do not create duplicates.
        $storeSub = StoreSubscription::where('stripe_subscription_id', $subscription->id)->first()
            ?: ($store->storesubscription ?: new StoreSubscription);

        $storeSub->store_id = $store->id;
        $storeSub->stripe_subscription_id = $subscription->id;
        $storeSub->subscription_status = $subscription->status;
        $storeSub->stripe_price_id = $priceId;
        $storeSub->stripe_plan_id = $planId;
        $storeSub->current_period_start = Carbon::createFromTimestamp($subscription->current_period_start);
        $storeSub->current_period_end = Carbon::createFromTimestamp($subscription->current_period_end);
        $storeSub->is_active = 1;
        $storeSub->save();

        return $storeSub;
    }

    private function cancelIncompleteSubscriptions(StripeClient $stripe, string $stripeCustomerId): void
    {
        try {
            $incomplete = $stripe->subscriptions->all([
                'customer' => $stripeCustomerId,
                'status' => 'incomplete',
                'limit' => 10,
            ]);
            foreach ($incomplete->data as $sub) {
                try {
                    $stripe->subscriptions->cancel($sub->id);
                } catch (ApiErrorException $e) {
                    // ignore individual cancel failures
                }
            }
        } catch (ApiErrorException $e) {
            // ignore listing failures
        }
    }
}
