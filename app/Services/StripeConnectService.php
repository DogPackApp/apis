<?php

namespace App\Services;

use Stripe\StripeClient;

class StripeConnectService
{
    public function createExpressAccount(string $countryShortName, string $serviceAgreement, string $stripeKey): string
    {
        $account = $this->client($stripeKey)->accounts->create([
            'type' => 'express',
            'country' => $countryShortName,
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
            'tos_acceptance' => [
                'service_agreement' => $serviceAgreement,
            ],
            'business_profile' => [
                'url' => config('services.marketplace_fe_url'),
                'mcc' => '5995', // Pet Shops, Pet Foods, and Supplies
            ],
        ]);

        return $account->id;
    }

    public function createAccountLink(string $accountId, string $stripeKey): string
    {
        $accountLink = $this->client($stripeKey)->accountLinks->create([
            'account' => $accountId,
            'return_url' => config('services.marketplace_fe_url').'/success',
            'refresh_url' => config('services.marketplace_fe_url').'/payout',
            'type' => 'account_onboarding',
        ]);

        return $accountLink->url;
    }

    protected function client(string $stripeKey): StripeClient
    {
        return new StripeClient(['api_key' => $stripeKey]);
    }
}
