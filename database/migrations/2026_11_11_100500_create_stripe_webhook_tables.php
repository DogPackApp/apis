<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('store_id')->nullable();
            $table->string('description')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('dispute_stripe_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('stripe_event_id')->unique();
            $table->string('event_type');
            $table->string('dispute_id')->nullable();
            $table->string('payment_intent_id')->nullable();
            $table->string('charge_id')->nullable();
            $table->integer('amount')->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('reason')->nullable();
            $table->string('status')->nullable();
            $table->json('store_order_ids')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('store_subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('store_id');
            $table->string('stripe_subscription_id', 255)->nullable();
            $table->string('subscription_status', 50)->nullable();
            $table->string('stripe_price_id', 255)->nullable();
            $table->string('stripe_plan_id', 255)->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->tinyInteger('is_active')->nullable()->default(1);
            $table->timestamps();
        });

        Schema::create('store_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('store_id');
            $table->string('stripe_invoice_id', 255);
            $table->decimal('amount', 10);
            $table->string('currency', 10)->nullable()->default('usd');
            $table->string('status', 50)->nullable()->default('paid');
            $table->timestamp('paid_at')->nullable();
            $table->string('billing_reason', 50)->nullable();
            $table->text('receipt_url')->nullable();
            $table->text('failure_message')->nullable();
            $table->string('avalara_transaction_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_payments');
        Schema::dropIfExists('store_subscriptions');
        Schema::dropIfExists('dispute_stripe_logs');
        Schema::dropIfExists('stripe_logs');
    }
};
