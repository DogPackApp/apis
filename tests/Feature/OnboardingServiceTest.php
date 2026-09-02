<?php

use App\Enums\OnboardingStep;
use App\Models\Seller\Seller;
use App\Services\OnboardingService;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('initiating onboarding sets every step to zero', function () {
    $seller = Seller::factory()->create();

    $onboarding = (new OnboardingService)->initiateOnboarding($seller)->fetchOnboardingStatus($seller);

    foreach (OnboardingStep::cases() as $step) {
        expect($onboarding->{$step->value})->toBe(0);
    }
});

test('completing a step flips only that flag', function () {
    $seller = Seller::factory()->create();
    $service = new OnboardingService;
    $service->initiateOnboarding($seller);

    $service->complete($seller, OnboardingStep::Product);

    $onboarding = $service->fetchOnboardingStatus($seller);

    expect($onboarding->is_product)->toBe(1)
        ->and($onboarding->is_shipping)->toBe(0);
});

test('onboarding is complete only once every step is done', function () {
    $seller = Seller::factory()->create();
    $service = new OnboardingService;
    $service->initiateOnboarding($seller);

    expect($service->isComplete($seller))->toBeFalse();

    foreach (OnboardingStep::cases() as $step) {
        $service->complete($seller, $step);
    }

    expect($service->isComplete($seller))->toBeTrue();
});
