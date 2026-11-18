<?php

use App\Models\Order\Customers;
use App\Models\Order\PaymentDetail;
use App\Models\Order\StoreOrder;
use App\Services\Order\CustomerPurchaseStatusService;
use Tests\Concerns\InteractsWithSellerAuth;
use Tests\TestCase;

uses(TestCase::class, InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('a blocked customer email or payment fingerprint cannot purchase', function () {
    $order = new StoreOrder;
    $order->save();

    $customer = new Customers;
    $customer->store_order_id = $order->id;
    $customer->user_id = 44;
    $customer->name = 'Blocked Buyer';
    $customer->email = 'Buyer@Example.com';
    $customer->shipping_address = ['line' => '1 Main'];
    $customer->purchase_status = Customers::PURCHASE_STATUS_BLOCKED;
    $customer->save();

    $payment = new PaymentDetail;
    $payment->store_order_id = $order->id;
    $payment->payment_fingerprint = 'fp_blocked';
    $payment->save();

    $service = new CustomerPurchaseStatusService;

    expect($service->canPurchase(null, 'buyer@example.com', null))->toBeFalse()
        ->and($service->canPurchase(44, null, null))->toBeFalse()
        ->and($service->canPurchase(null, null, 'fp_blocked'))->toBeFalse()
        ->and($service->canPurchase(null, 'someone@example.com', null))->toBeTrue()
        ->and($service->canPurchase(null, null, null))->toBeTrue();
});
