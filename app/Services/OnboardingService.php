<?php

namespace App\Services;

use App\Enums\OnboardingStep;
use App\Models\Seller\OnboardingStatus;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
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

        foreach (OnboardingStep::cases() as $step) {
            $onboarding->{$step->value} = 0;
        }

        $onboarding->save();

        return $this;
    }

    public function complete(Seller $seller, OnboardingStep $step, ?Store $store = null): OnboardingStatus
    {
        $onboarding = $this->fetchOnboardingStatus($seller) ?? tap(new OnboardingStatus, function (OnboardingStatus $onboarding) use ($seller): void {
            $onboarding->seller_id = $seller->id;
        });

        $onboarding->{$step->value} = 1;

        if ($store && ! $onboarding->store_id) {
            $onboarding->store_id = $store->id;
        }

        $onboarding->save();

        return $onboarding;
    }

    public function isComplete(Seller $seller): bool
    {
        $onboarding = $this->fetchOnboardingStatus($seller);

        if (! $onboarding) {
            return false;
        }

        foreach (OnboardingStep::cases() as $step) {
            if (! $onboarding->{$step->value}) {
                return false;
            }
        }

        return true;
    }

    public function fetchOnboardingStatus(Seller $seller): OnboardingStatus|Model|null
    {
        return OnboardingStatus::query()
            ->where('seller_id', $seller->id)
            ->first();
    }
}
