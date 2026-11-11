<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('stripe_customer_id', 255)->nullable();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
        });

        Schema::create('pricings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->nullable();
            $table->string('stripe_price_id', 255)->nullable()->unique('stripe_price_id');
            $table->string('price_id', 255)->nullable();
            $table->string('type', 20)->nullable();
            $table->decimal('amount', 10)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('marketplace_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('plan_key', 50)->unique();
            $table->string('plan_id', 255);
            $table->timestamps();
        });

        Schema::create('marketplace_plan_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('plan_id');
            $table->string('country_code', 10);
            $table->uuid('uuid')->nullable();
            $table->string('country')->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('currency_symbol', 8)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('price_id', 255)->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('marketplace_plans')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_plan_prices');
        Schema::dropIfExists('marketplace_plans');
        Schema::dropIfExists('pricings');

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['stripe_customer_id', 'pm_type', 'pm_last_four']);
        });
    }
};
