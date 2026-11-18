<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Services\StripeKeyService;
use App\Services\Subscribe\PlanService;
use DB;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;
use Validator;

class StripeestimateNewPlanCostController extends Controller
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
                'address_line1' => 'required|string',
                'address_line2' => 'nullable|string',
                'city' => 'required|string',
                'state' => 'required|string',
                'postal_code' => 'required|string',
                'country_code' => 'required|string',
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

            // Initialize Stripe client
            $stripe = new StripeClient(StripeKeyService::key(auth()->user()->store->country_id));

            $address = [
                'line1' => $request->address_line1 ?? '',
                'line2' => $request->address_line2 ?? '',
                'city' => $request->city ?? '',
                'state' => $request->state ?? '',
                'postal_code' => $request->postal_code ?? '',
                'country' => $request->country_code ?? '',
            ];

            // Simulate a customer to calculate the price and taxes without an actual customer or subscription
            $fakeCustomer = $stripe->customers->create([
                'email' => 'dilip@dogpackapp.com', // Use a dummy email, no need for a real customer
                'address' => $address,
            ]);

            // Fetch the upcoming invoice for the new plan (without subscription)
            $newInvoice = $stripe->invoices->upcoming([
                'customer' => $fakeCustomer->id, // Use the fake customer created above
                'subscription_items' => [
                    ['price' => $request->price_id], // Using the new plan price
                ],
                'automatic_tax' => ['enabled' => true],  // Enable automatic tax calculation
            ]);

            // Calculate the estimated amount with tax
            $newAmountExcludingTax = $newInvoice->subtotal; // Base price before tax
            $newAmountWithTax = $newInvoice->total; // Total price including tax
            $totalTax = $newInvoice->tax; // Total price including tax

            // Clean up by deleting the fake customer (since we only used it to calculate the price)
            $stripe->customers->delete($fakeCustomer->id);

            return $this->successResponseWithData([
                'price' => $newAmountExcludingTax / 100,  // Price for the new plan (excluding tax)
                'tax' => $totalTax / 100,  // Total tax for the new plan
                'total_price' => $newAmountWithTax / 100,  // Total price (price + tax)
                'currency' => $newInvoice->currency,
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
