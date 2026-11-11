<?php

use App\Mail\Stripe\StripeApprovedMail;
use App\Models\Order\PaymentDetail;
use App\Models\Order\StoreOrder;
use App\Models\Seller\OnboardingStatus;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use App\Models\Stripe\StoreSubscription;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();

    Config::set('services.marketplace_stripe', [
        'key' => 'sk_test_webhook',
        'wb_secret' => 'whsec_account',
        'wb_stripe_payment_capture' => 'whsec_capture',
    ]);
});

test('an approved Stripe account marks the store verified', function () {
    Mail::fake();

    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create([
        'seller_id' => $seller->id,
        'stripe_account_id' => 'acct_test',
        'stripe_status' => Store::STRIPE_STATUS_PENDING,
    ]);
    OnboardingStatus::query()->create(['seller_id' => $seller->id]);

    $payload = json_encode([
        'id' => 'evt_account',
        'object' => 'event',
        'type' => 'account.updated',
        'data' => [
            'object' => [
                'id' => 'acct_test',
                'object' => 'account',
                'details_submitted' => true,
                'charges_enabled' => true,
                'payouts_enabled' => true,
                'requirements' => [
                    'currently_due' => [],
                    'past_due' => [],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        '/api/seller/stripe/webhook',
        [],
        [],
        [],
        [
            'HTTP_STRIPE_SIGNATURE' => stripeWebhookSignature($payload, 'whsec_account'),
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload,
    )->assertOk()->assertJsonPath('status', 'success');

    expect($store->fresh()->stripe_status)->toBe(Store::STRIPE_STATUS_VERIFIED);
    expect(OnboardingStatus::query()->where('seller_id', $seller->id)->value('is_finance'))->toBe(1);
    Mail::assertQueued(StripeApprovedMail::class);
});

test('a captured payment marks the order paid', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    $order = StoreOrder::query()->create([
        'store_id' => $store->id,
        'order_no' => '3003',
        'payment_status' => 'pending',
        'admin_status' => StoreOrder::ADMIN_STATUS_DEFAULT,
    ]);
    PaymentDetail::query()->forceCreate([
        'store_order_id' => $order->id,
        'payment_id' => 'pi_test',
    ]);

    $payload = json_encode([
        'id' => 'evt_pay',
        'object' => 'event',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_test',
                'object' => 'payment_intent',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        '/api/seller/stripe/webhook/paymentcapture',
        [],
        [],
        [],
        [
            'HTTP_STRIPE_SIGNATURE' => stripeWebhookSignature($payload, 'whsec_capture'),
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload,
    )->assertOk();

    expect($order->fresh()->payment_status)->toBe(StoreOrder::ORDER_PAYMENT_STATUS_PAID);
});

test('a paid subscription invoice activates the store subscription', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    OnboardingStatus::query()->create(['seller_id' => $seller->id]);
    $subscription = StoreSubscription::query()->create([
        'store_id' => $store->id,
        'stripe_subscription_id' => 'sub_test',
        'subscription_status' => 'incomplete',
        'is_active' => 0,
    ]);

    $this->postJson('/api/seller/stripe/webhook/subscription', [
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => 'in_test',
                'subscription' => 'sub_test',
                'amount_paid' => 2900,
                'currency' => 'usd',
                'tax' => 0,
                'invoice_pdf' => 'https://example.com/invoice.pdf',
                'billing_reason' => 'subscription_create',
                'status_transitions' => ['paid_at' => now()->timestamp],
                'lines' => [
                    'data' => [[
                        'type' => 'subscription',
                        'period' => [
                            'start' => now()->timestamp,
                            'end' => now()->addMonth()->timestamp,
                        ],
                    ]],
                ],
            ],
        ],
    ])->assertOk()->assertJsonPath('received', true);

    $subscription->refresh();
    expect($subscription->subscription_status)->toBe('active');
    expect((int) $subscription->is_active)->toBe(1);
    expect(OnboardingStatus::query()->where('seller_id', $seller->id)->value('is_subscribe'))->toBe(1);
});

function stripeWebhookSignature(string $payload, string $secret): string
{
    $timestamp = time();
    $signed = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

    return "t={$timestamp},v1={$signed}";
}
