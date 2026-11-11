<?php

use App\Models\Seller\OnboardingStatus;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use App\Models\Stripe\StorePayment;
use App\Models\Stripe\StoreSubscription;
use App\Models\Subscribe\MarketplacePlan;
use App\Models\Subscribe\MarketplacePlanPrice;
use App\Models\Subscribe\Pricing;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
    Cache::flush();
});

test('a seller can list plans, prices, the current plan, and invoices', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $plan = MarketplacePlan::query()->create([
        'plan_key' => 'monthly',
        'plan_id' => 'prod_monthly',
    ]);
    MarketplacePlanPrice::query()->create([
        'plan_id' => $plan->id,
        'country_code' => 'CA',
        'uuid' => '11111111-1111-1111-1111-111111111111',
        'country' => 'Canada',
        'currency' => 'cad',
        'currency_symbol' => '$',
        'amount' => 29,
        'price_id' => 'price_monthly_ca',
    ]);
    Pricing::query()->create([
        'name' => 'Monthly',
        'stripe_price_id' => 'price_monthly_ca',
        'amount' => 29,
        'status' => true,
    ]);

    $this->getJson('/api/seller/subscribe/plans')
        ->assertOk()
        ->assertJsonPath('message', 'Plans fetched successfully')
        ->assertJsonPath('data.0.plan_id', 'prod_monthly')
        ->assertJsonPath('data.0.prices.CA.price_id', 'price_monthly_ca');

    $this->getJson('/api/seller/subscribe/pricings')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Monthly')
        ->assertJsonPath('data.0.stripe_id', 'price_monthly_ca');

    $this->getJson('/api/seller/subscribe/currentPlan')
        ->assertNotFound()
        ->assertJsonPath('errors', 'No active subscription found');

    StoreSubscription::query()->create([
        'store_id' => $store->id,
        'stripe_subscription_id' => 'sub_test',
        'subscription_status' => 'active',
        'stripe_price_id' => 'price_monthly_ca',
        'stripe_plan_id' => 'prod_monthly',
        'current_period_start' => now(),
        'current_period_end' => now()->addMonth(),
        'is_active' => 1,
    ]);
    StorePayment::query()->create([
        'store_id' => $store->id,
        'stripe_invoice_id' => 'in_test',
        'amount' => 29,
        'currency' => 'cad',
        'status' => StorePayment::STATUS_PAID,
    ]);

    $this->getJson('/api/seller/subscribe/currentPlan')
        ->assertOk()
        ->assertJsonPath('data.current_plan.plan_type', 'monthly')
        ->assertJsonPath('data.subscription.stripe_subscription_id', 'sub_test');

    $this->postJson('/api/seller/subscribe/list')
        ->assertOk()
        ->assertJsonPath('data.0.stripe_invoice_id', 'in_test');

    $this->getJson('/api/seller/subscribe/invoicePdf?invoice_id=missing')
        ->assertNotFound()
        ->assertJsonPath('errors', 'Invoice not found');
});

test('billing actions that do not call stripe stay local', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create([
        'seller_id' => $seller->id,
        'pm_type' => 'visa',
        'pm_last_four' => '4242',
    ]);
    OnboardingStatus::query()->create([
        'seller_id' => $seller->id,
        'is_subscribe' => 1,
    ]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $this->postJson('/api/seller/subscribe/getCards')
        ->assertBadRequest()
        ->assertJsonPath('errors', "You haven't subscribe to any plan yet!");

    $this->postJson('/api/seller/subscribe/addCard', [])
        ->assertUnprocessable();

    $this->postJson('/api/seller/subscribe/updateDefault', [])
        ->assertUnprocessable();

    $this->postJson('/api/seller/subscribe/deleteCard', [])
        ->assertUnprocessable();

    $this->postJson('/api/seller/subscribe/changeSubscriptionPlan', [])
        ->assertUnprocessable();

    $this->postJson('/api/seller/subscribe/approximateAmountDifference', [
        'plan_id' => 'missing',
        'price_id' => 'missing',
    ])->assertUnprocessable();

    $this->postJson('/api/seller/subscribe/estimateNewPlanCost', [])
        ->assertUnprocessable();

    $this->postJson('/api/seller/subscribe/plan', [])
        ->assertUnprocessable();

    $this->putJson('/api/seller/subscribe/retryPayment')
        ->assertNotFound()
        ->assertJsonPath('errors', 'Subscription not found');

    $this->getJson('/api/seller/subscribe/calculateNextBilling')
        ->assertNotFound();

    $subscription = StoreSubscription::query()->create([
        'store_id' => $store->id,
        'stripe_subscription_id' => 'sub_clear',
        'is_active' => 1,
    ]);
    StorePayment::query()->create([
        'store_id' => $store->id,
        'stripe_invoice_id' => 'in_clear',
        'amount' => 10,
    ]);
    $store->update([
        'stripe_customer_id' => 'cus_test',
        'pm_type' => 'visa',
        'pm_last_four' => '4242',
    ]);

    $this->postJson('/api/seller/subscribe/clearSubscription', [
        'store_id' => $store->id,
    ])->assertOk();

    expect(StoreSubscription::query()->whereKey($subscription->id)->exists())->toBeFalse()
        ->and(StorePayment::query()->where('store_id', $store->id)->exists())->toBeFalse()
        ->and($store->fresh()->stripe_customer_id)->toBeNull()
        ->and(OnboardingStatus::query()->where('seller_id', $seller->id)->value('is_subscribe'))->toBe(0);
});
