<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Models\Store\Store;
use App\Services\Subscribe\PlanService;
use Illuminate\Http\Request;

class StripeCurrentPlanController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request)
    {
        $currentPlan = Store::with('storesubscription')->where('id', auth()->user()->store->id)->first();

        if (! $currentPlan->storesubscription) {
            return $this->errorResponse('No active subscription found', 404);
        }

        $subscription = $currentPlan->storesubscription;
        $planService = new PlanService;

        // Find the plan details based on stripe_price_id
        $planDetails = $this->findPlanByPriceId($planService, $subscription->stripe_price_id);

        if (! $planDetails) {
            return $this->errorResponse('Plan not found', 404);
        }

        $response = [
            'store' => [
                'id' => $currentPlan->id,
                'name' => $currentPlan->name,
                'slug' => $currentPlan->slug,
                'stripe_customer_id' => $currentPlan->stripe_customer_id,
                'pm_type' => $currentPlan->pm_type,
                'pm_last_four' => $currentPlan->pm_last_four,
            ],
            'current_plan' => [
                'plan_type' => $planDetails['plan_type'], // monthly or yearly
                'plan_name' => ucfirst($planDetails['plan_type']).' Plan',
                'country' => $planDetails['country'],
                'currency' => $planDetails['currency'],
                'currency_symbol' => $planDetails['currency_symbol'],
                'amount' => $planDetails['amount'],
                'stripe_plan_id' => $subscription->stripe_plan_id,
                'stripe_price_id' => $subscription->stripe_price_id,
            ],
            'subscription' => [
                'stripe_subscription_id' => $subscription->stripe_subscription_id,
                'subscription_status' => $subscription->subscription_status,
                'current_period_start' => $subscription->current_period_start,
                'current_period_end' => $subscription->current_period_end,
                'is_active' => $subscription->is_active,
            ],
        ];

        return $this->successResponseWithData($response);
    }

    private function findPlanByPriceId($planService, $priceId)
    {
        $plans = $planService->getAllPlans();

        foreach ($plans as $planType => $plan) {
            foreach ($plan['prices'] as $countryCode => $price) {
                if ($price['price_id'] === $priceId) {
                    return [
                        'plan_type' => $planType,
                        'plan_id' => $plan['plan_id'],
                        'country' => $price['country'],
                        'currency' => $price['currency'],
                        'currency_symbol' => $price['currency_symbol'],
                        'amount' => $price['amount'],
                        'country_code' => $countryCode,
                    ];
                }
            }
        }

        return null;
    }
}
