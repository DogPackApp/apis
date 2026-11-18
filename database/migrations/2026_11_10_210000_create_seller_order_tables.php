<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_orders', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            $table->string('payment_status')->default('unfulfilled');
            $table->string('dispute_status')->nullable();
            $table->string('order_status')->default('unfulfilled');
            $table->string('fulfilment_status')->default('unfulfilled');
            $table->string('order_no')->nullable()->index();
            $table->string('shipping_method')->nullable();
            $table->json('shipping')->nullable();
            $table->timestamp('fulfilment_date')->nullable();
            $table->string('source_id', 255)->nullable()->unique();
            $table->tinyInteger('admin_status')->nullable()->default(0);
            $table->text('admin_reason')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'created_at'], 'store_orders_store_created_idx');
            $table->index(['store_id', 'fulfilment_status', 'created_at'], 'store_orders_fulfil_created_idx');
            $table->index(['store_id', 'order_status', 'created_at'], 'store_orders_status_created_idx');
            $table->index(['store_id', 'payment_status', 'created_at'], 'store_orders_payment_created_idx');
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('store_order_id');
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->json('shipping_address');
            $table->json('billing_address')->nullable();
            $table->timestamps();
        });

        Schema::create('order_billings', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('store_order_id')->index();
            $table->integer('subtotal');
            $table->integer('discount')->default(0);
            $table->integer('dp_contribution')->default(0);
            $table->json('promo')->nullable();
            $table->integer('seller_contribution')->default(0);
            $table->unsignedInteger('subscribe_save_cancel_fee')->default(0);
            $table->integer('wallet_discount')->default(0);
            $table->json('wallet')->nullable();
            $table->integer('tax_rate')->nullable();
            $table->json('tax_breakdown')->nullable();
            $table->integer('tax')->default(0);
            $table->integer('shipping_rate')->default(0);
            $table->integer('total')->default(0);
            $table->integer('stripe_fee')->default(0);
            $table->decimal('exchange_rate', 10, 4)->nullable();
            $table->json('currency_details')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_details', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('store_order_id')->index();
            $table->string('payment_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('payment_method')->default('pm_stripe');
            $table->json('card')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('store_order_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('subscription_id')->nullable()->index();
            $table->boolean('is_subscribe_save')->default(false);
            $table->integer('subscribe_save_seller_percent')->nullable();
            $table->integer('subscribe_save_dogpack_percent')->nullable();
            $table->json('promo')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->integer('original_price');
            $table->integer('discount')->default(0);
            $table->integer('price');
            $table->json('tax')->nullable();
            $table->integer('subtotal');
            $table->integer('refunded')->default(0);
            $table->json('item_options')->nullable();
            $table->json('currency_details')->nullable();
            $table->json('product_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->nullable();
            $table->unsignedBigInteger('store_order_id')->index();
            $table->string('refund_id');
            $table->unsignedBigInteger('order_item_id')->nullable()->index();
            $table->integer('amount')->nullable();
            $table->integer('converted')->nullable();
            $table->integer('tax')->nullable();
            $table->integer('tax_converted')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('refund_decisions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('refund_id')->unique();
            $table->string('status')->default('pending');
            $table->string('replacement_order_no')->nullable()->unique();
            $table->string('type');
            $table->unsignedInteger('quantity')->nullable();
            $table->unsignedInteger('refunded_after')->nullable();
            $table->unsignedInteger('super_admin_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('easy_posts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('uuid', 36);
            $table->unsignedBigInteger('store_order_id')->index();
            $table->string('via', 255)->nullable();
            $table->json('shipment')->nullable();
            $table->json('rates')->nullable();
            $table->integer('subtotal')->nullable();
            $table->integer('total')->nullable();
            $table->string('trk_id', 255)->nullable()->index();
            $table->string('status', 255)->nullable();
            $table->string('refund_status', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('manual_fulfillments', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('uuid', 36);
            $table->unsignedBigInteger('store_order_id')->index();
            $table->string('tracking')->nullable();
            $table->string('carrier')->nullable();
            $table->timestamps();
        });

        Schema::create('timelines', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('uuid', 36);
            $table->unsignedBigInteger('store_order_id');
            $table->string('event');
            $table->string('additional_data')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('avalaras', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('store_order_id');
            $table->string('code', 255);
        });

        Schema::create('payouts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('uuid', 36);
            $table->string('stripe_txn_id')->nullable()->unique();
            $table->unsignedBigInteger('store_id');
            $table->timestamp('payout_date');
            $table->string('status')->default('upcoming');
            $table->integer('amount')->default(0);
            $table->integer('payout_amount')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status', 'created_at'], 'payouts_store_status_created_idx');
        });

        Schema::create('payout_details', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('uuid', 36);
            $table->unsignedBigInteger('payout_id')->index();
            $table->unsignedBigInteger('store_order_id')->index();
            $table->integer('commission');
            $table->integer('amount')->nullable();
            $table->boolean('collection')->default(false);
            $table->timestamps();
        });

        Schema::create('export_orders', function (Blueprint $table): void {
            $table->uuid('batch_id')->primary();
            $table->unsignedBigInteger('store_id');
            $table->json('filters')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->json('errors')->nullable();
        });

        Schema::create('store_settings', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('uuid', 36);
            $table->unsignedBigInteger('store_id')->index();
            $table->integer('payout_days')->default(28);
            $table->integer('commission')->default(7);
            $table->decimal('promo_contribution_percent', 8, 2)->nullable();
            $table->integer('subscribe_save_discount_percent')->nullable();
            $table->integer('dogpack_subscribe_save_first_order_percent')->nullable();
            $table->boolean('subscribe_save_disabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
        Schema::dropIfExists('export_orders');
        Schema::dropIfExists('payout_details');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('avalaras');
        Schema::dropIfExists('timelines');
        Schema::dropIfExists('manual_fulfillments');
        Schema::dropIfExists('easy_posts');
        Schema::dropIfExists('refund_decisions');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('payment_details');
        Schema::dropIfExists('order_billings');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('store_orders');
    }
};
