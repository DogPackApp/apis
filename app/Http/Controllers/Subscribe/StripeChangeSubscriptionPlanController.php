<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Models\Store\Store;
use App\Services\StripeKeyService;
use App\Services\Subscribe\PlanService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;

class StripeChangeSubscriptionPlanController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request)
    {
        DB::beginTransaction();
        try {

            $validator = Validator::make($request->all(), [
                'plan_id' => [
                    'required',
                    'string',
                ],
                'price_id' => [
                    'required',
                    'string',
                ],
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

            $store = Store::with('storesubscription')->where('id', auth()->user()->store->id)->first();

            $stripe = new StripeClient(StripeKeyService::key($store->country_id));

            // Ensure the store has a Stripe customer ID
            if (! $store->stripe_customer_id) {
                return $this->errorResponse('You haven\'t subscribe to any plan yet!', Response::HTTP_BAD_REQUEST);
            }

            $currentSub = $store->storesubscription;

            if (! $currentSub || ! $currentSub->stripe_subscription_id) {
                return $this->errorResponse('No active subscription found', 400);
            }

            if ($request->card_id) {

                //
                // 1) Attach & set default card (on both customer & subscription)
                //
                // Retrieve existing payment method from Stripe
                $paymentMethod = $stripe->paymentMethods->retrieve($request->card_id);

                // If it's not already attached to this customer, attach it
                if ($paymentMethod->customer !== $store->stripe_customer_id) {
                    $stripe->paymentMethods->attach($request->card_id, [
                        'customer' => $store->stripe_customer_id,
                    ]);
                }

                // 1a) Set as default for the subscription
                $stripe->subscriptions->update(
                    $currentSub->stripe_subscription_id,
                    ['default_payment_method' => $request->card_id]
                );

                // 1b) Also set it as default for all future invoices on the customer
                $stripe->customers->update($store->stripe_customer_id, [
                    'invoice_settings' => [
                        'default_payment_method' => $request->card_id,
                    ],
                ]);

                // 1c) Update local store record with card brand + last4 (if it's a card object)
                if (isset($paymentMethod->card)) {
                    $store->pm_type = $paymentMethod->card->brand;
                    $store->pm_last_four = $paymentMethod->card->last4;
                    $store->save();
                }
            }

            $updatedSubscription = $stripe->subscriptions->update(
                $currentSub->stripe_subscription_id,
                [
                    'items' => [[
                        'id' => $stripe->subscriptions->retrieve($currentSub->stripe_subscription_id)->items->data[0]->id,
                        'price' => $request->price_id,
                    ]],
                    'proration_behavior' => 'create_prorations',
                    'automatic_tax' => ['enabled' => true],
                ]
            );

            $currentSub->stripe_plan_id = $request->plan_id;
            $currentSub->stripe_price_id = $request->price_id;
            $currentSub->subscription_status = $updatedSubscription->status;
            $currentSub->current_period_start = Carbon::createFromTimestamp(
                $updatedSubscription->current_period_start
            );
            $currentSub->current_period_end = Carbon::createFromTimestamp(
                $updatedSubscription->current_period_end
            );
            $currentSub->save();

            DB::commit();

            return $this->successResponse('Your subscription plan has been updated successfully');
        } catch (ApiErrorException $e) {
            DB::rollBack();

            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(app()->isProduction() ? 'Something went wrong.' : $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

    }
}
