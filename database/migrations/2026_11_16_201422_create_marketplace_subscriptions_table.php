<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_subscriptions')) {
            return;
        }

        Schema::create('marketplace_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('email')->index();
            $table->unsignedBigInteger('store_id')->index();
            $table->unsignedBigInteger('item_id')->index();
            $table->unsignedBigInteger('item_variant_id')->nullable()->index();
            $table->unsignedInteger('quantity');
            $table->text('customisation_text')->nullable();
            $table->json('customisation_media')->nullable();
            $table->unsignedSmallInteger('frequency_days');
            $table->string('status', 32)->default('active')->index();
            $table->dateTime('origin_order_at');
            $table->dateTime('next_order_at')->index();
            $table->json('shipping_address');
            $table->unsignedInteger('frozen_shipping_amount');
            $table->string('frozen_shipping_method')->nullable();
            $table->unsignedInteger('country_id');
            $table->char('currency', 3);
            $table->string('stripe_customer_id');
            $table->string('stripe_payment_method_id');
            $table->unsignedTinyInteger('locked_seller_discount_percent');
            $table->unsignedTinyInteger('dogpack_first_order_percent_applied');
            $table->boolean('auto_apply_rewards')->default(false);
            $table->unsignedInteger('last_paid_item_price');
            $table->unsignedInteger('price_rise_notified_unit_price')->nullable();
            $table->dateTime('price_rise_notified_at')->nullable();
            $table->unsignedTinyInteger('payment_attempt_count')->default(0);
            $table->dateTime('paused_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->boolean('oos_catchup_pending')->default(false);
            $table->unsignedBigInteger('last_paid_store_order_id')->nullable();
            $table->string('manage_token_hash')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_order_at']);
            $table->index(['item_id', 'status']);

            $table->foreign('store_id')->references('id')->on('stores')->restrictOnDelete();
            $table->foreign('item_id')->references('id')->on('items')->restrictOnDelete();
            $table->foreign('item_variant_id')->references('id')->on('item_variants')->nullOnDelete();
            $table->foreign('last_paid_store_order_id')->references('id')->on('store_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_subscriptions');
    }
};
