<?php

namespace App\Services\Subscribe;

use App\Models\Subscribe\MarketplacePlan;
use Illuminate\Support\Facades\Cache;

class PlanService
{
    /**
     * @var array<string, string>
     */
    protected array $supportedCountries = [
        'US' => 'United States',
        'CA' => 'Canada',
        'GB' => 'United Kingdom',
        'AU' => 'Australia',
        'IE' => 'Ireland',
        'NZ' => 'New Zealand',
    ];

    protected const CACHE_KEY = 'marketplace_plans_data';

    protected const CACHE_DURATION = 86400;

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function getPlans(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_DURATION, function () {
            $plans = [];

            $marketplacePlans = MarketplacePlan::with('prices')->get();

            foreach ($marketplacePlans as $plan) {
                $planKey = $plan->plan_key;
                $prices = [];

                foreach ($plan->prices as $price) {
                    $prices[$price->country_code] = [
                        'uuid' => $price->uuid,
                        'country' => $price->country,
                        'currency' => $price->currency,
                        'currency_symbol' => $price->currency_symbol,
                        'amount' => (float) $price->amount,
                        'price_id' => $price->price_id,
                    ];
                }

                $plans[$planKey] = [
                    'id' => $plan->id,
                    'name' => ucwords($planKey),
                    'plan_id' => $plan->plan_id,
                    'prices' => $prices,
                ];
            }

            return $plans;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPlanPriceByUuid(string $uuid): ?array
    {
        $plans = $this->getPlans();

        foreach ($plans as $planKey => $plan) {
            foreach ($plan['prices'] as $countryCode => $price) {
                if (isset($price['uuid']) && $price['uuid'] === $uuid) {
                    return [
                        'plan_key' => $planKey,
                        'plan' => [
                            'id' => $plan['id'],
                            'plan_id' => $plan['plan_id'],
                        ],
                        'price_key' => $countryCode,
                        'price' => $price,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAllPlans(): array
    {
        return $this->getPlans();
    }

    public function clearCache(): bool
    {
        return Cache::forget(self::CACHE_KEY);
    }
}
