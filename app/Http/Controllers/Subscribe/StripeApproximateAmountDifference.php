<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Models\Store\Store;
use App\Services\StripeKeyService;
use App\Services\Subscribe\PlanService;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;
use Validator;

class StripeApproximateAmountDifference extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request)
    {
        DB::beginTransaction(); // Start transaction
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

            // Get the store's current subscription
            $store = Store::with('storesubscription')->where('id', auth()->user()->store->id)->first();

            if (! $store->storesubscription || ! $store->storesubscription->is_active) {
                return $this->errorResponse(['Store does not have an active subscription.'], Response::HTTP_NOT_FOUND);
            }

            // Initialize Stripe client
            $stripe = new StripeClient(StripeKeyService::key($store->country_id));

            $currentSubscription = $store->storesubscription;

            // Fetch the upcoming invoice for the current plan (without updating the subscription)
            $currentInvoice = $stripe->invoices->upcoming([
                'customer' => $store->stripe_customer_id,
                'subscription' => $currentSubscription->stripe_subscription_id,
                'automatic_tax' => ['enabled' => true],  // Enable automatic tax calculation
            ]);

            // Fetch the upcoming invoice for the new plan (without updating the subscription)
            $newInvoice = $stripe->invoices->upcoming([
                'customer' => $store->stripe_customer_id,
                'subscription_items' => [
                    ['price' => $request->price_id], // Using the new plan price
                ],
                'automatic_tax' => ['enabled' => true],  // Enable automatic tax calculation
            ]);

            // Fetch taxes from both the current and new invoices
            $currentTaxes = $currentInvoice->taxes ?? [];
            $newTaxes = $newInvoice->taxes ?? [];

            // Calculate the difference in the amount due, including taxes
            $currentAmountWithTax = $currentInvoice->total;
            $newAmountWithTax = $newInvoice->total;

            // Debugging: If taxes are not included, check if automatic_tax is enabled
            if (empty($currentTaxes)) {
                Log::info('No taxes for current subscription. Automatic tax: '.($currentInvoice->automatic_tax->enabled ? 'Enabled' : 'Disabled'));
            }

            if (empty($newTaxes)) {
                Log::info('No taxes for new plan. Automatic tax: '.($newInvoice->automatic_tax->enabled ? 'Enabled' : 'Disabled'));
            }

            // Calculate the difference between the new and current total amounts
            $amountDifference = $newAmountWithTax - $currentAmountWithTax;

            return $this->successResponse([
                'approximate_amount_difference_with_tax' => $amountDifference / 100,  // Show the difference in dollars
                'currency' => $currentInvoice->currency,
                'current_plan_amount' => $currentAmountWithTax / 100,  // Amount with tax for the current plan
                'new_plan_amount' => $newAmountWithTax / 100,  // Amount with tax for the new plan
                'current_tax' => $currentTaxes,   // Tax details for debugging
                'new_tax' => $newTaxes,           // Tax details for debugging
            ]);

        } catch (ApiErrorException $e) {
            DB::rollBack();

            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(app()->isProduction() ? 'Something went wrong.' : $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
