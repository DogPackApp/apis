<?php

use App\Mail\Finance\FinancePayoutExportEmail;
use App\Models\Order\OrderBilling;
use App\Models\Order\Payout;
use App\Models\Order\PayoutDetail;
use App\Models\Order\StoreOrder;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('a seller sees payout totals and one payout line', function () {
    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create([
        'seller_id' => $seller->id,
        'stripe_status' => Store::STRIPE_STATUS_PENDING,
    ]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    Payout::query()->forceCreate([
        'store_id' => $store->id,
        'status' => Payout::DEPOSITED,
        'amount' => 5000,
        'payout_date' => now()->subDay(),
    ]);
    $upcoming = Payout::query()->forceCreate([
        'store_id' => $store->id,
        'status' => Payout::UPCOMING,
        'amount' => 2000,
        'payout_date' => now(),
    ]);
    $order = StoreOrder::query()->create([
        'store_id' => $store->id,
        'order_no' => '4004',
        'payment_status' => StoreOrder::ORDER_PAYMENT_STATUS_PAID,
        'fulfilment_status' => StoreOrder::ORDER_FULFILLED,
        'admin_status' => StoreOrder::ADMIN_STATUS_DEFAULT,
    ]);
    OrderBilling::query()->forceCreate([
        'store_order_id' => $order->id,
        'subtotal' => 2500,
        'total' => 2500,
        'stripe_fee' => 0,
        'tax' => 0,
    ]);
    PayoutDetail::query()->forceCreate([
        'payout_id' => $upcoming->id,
        'store_order_id' => $order->id,
        'commission' => 0,
        'amount' => 2500,
        'collection' => false,
    ]);

    $this->postJson('/api/seller/stripe/payouts')
        ->assertOk()
        ->assertJsonPath('error', 'You stripe account is not yet activated.');

    $this->getJson('/api/seller/finance')
        ->assertOk()
        ->assertJsonPath('deposited', 50)
        ->assertJsonPath('upcoming', 20);

    $this->postJson('/api/seller/finance/'.$upcoming->uuid)
        ->assertOk()
        ->assertJsonPath('data.uuid', (string) $upcoming->uuid)
        ->assertJsonPath('data.details.0.order', '4004');
});

test('a seller can email a finance payout export', function () {
    Mail::fake();
    Storage::fake('mps3');

    $seller = Seller::factory()->verified()->create();
    $store = Store::factory()->create(['seller_id' => $seller->id]);
    Passport::actingAs($seller, ['seller'], 'marketplace');

    $payout = Payout::query()->forceCreate([
        'store_id' => $store->id,
        'status' => Payout::UPCOMING,
        'amount' => 1000,
        'payout_date' => now(),
    ]);

    $this->postJson('/api/seller/finance/export', [
        'payouts' => [(string) $payout->uuid],
    ])->assertOk()
        ->assertJsonPath('message', 'Finance export CSV file is being prepared. You will receive an email when it is ready.');

    Mail::assertSent(FinancePayoutExportEmail::class, function (FinancePayoutExportEmail $mail) use ($seller) {
        return $mail->hasTo($seller->email);
    });
});
