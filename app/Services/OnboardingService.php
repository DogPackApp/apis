<?php

namespace App\Services;

use App\Models\Seller\OnboardingStatus;
use App\Models\Seller\Seller;
use Illuminate\Database\Eloquent\Model;

class OnboardingService
{
    public function initiateOnboarding(Seller $seller): self
    {
        if ($this->fetchOnboardingStatus($seller)) {
            return $this;
        }

        $onboarding = new OnboardingStatus;
        $onboarding->seller_id = $seller->id;
        $onboarding->is_product = 0;
        $onboarding->is_subscribe = 0;
        $onboarding->is_store_setting = 0;
        $onboarding->is_finance = 0;
        $onboarding->save();

        return $this;
    }

    public function fetchOnboardingStatus(Seller $seller): OnboardingStatus|Model|null
    {
        return OnboardingStatus::query()
            ->where('seller_id', $seller->id)
            ->first();
    }
}
