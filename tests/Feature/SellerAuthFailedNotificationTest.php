<?php

use App\Notifications\Seller\SellerAuthFailed;
use App\Services\AuthFailureNotifier;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithSellerAuth;

uses(InteractsWithSellerAuth::class);

beforeEach(function () {
    $this->setUpSellerAuth();
});

test('auth failure notifier sends a slack notification', function () {
    Notification::fake();

    (new AuthFailureNotifier)->notify('login', 'seller@example.com', new RuntimeException('boom'));

    Notification::assertSentOnDemand(SellerAuthFailed::class, function (SellerAuthFailed $notification) {
        return $notification->event === 'login' && $notification->email === 'seller@example.com';
    });
});

test('seller:test-slack-alert command dispatches a slack notification', function () {
    Notification::fake();

    $this->artisan('seller:test-slack-alert', ['email' => 'test@example.com'])
        ->assertSuccessful();

    Notification::assertSentOnDemand(SellerAuthFailed::class);
});
