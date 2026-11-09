<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('store_id')->nullable()->constrained('stores');
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('store_id')->constrained('stores');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id'], 'shipping_zones_store_id_foreign');
        });

        Schema::create('shipping_zone_country', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones');
            $table->unsignedInteger('country_id')->nullable()->index('idx_country');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('country_id')->references('id')->on('countries');
        });

        Schema::create('shipping_zone_rates', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('shipping_zone_id')->constrained('shipping_zones');
            $table->unsignedBigInteger('shipping_rate_id');
            $table->unsignedInteger('currency_id')->nullable()->index('idx_currency');
            $table->tinyInteger('shipping_type');
            $table->integer('price');
            $table->tinyInteger('condition_type')->nullable();
            $table->integer('low_value')->nullable();
            $table->integer('high_value')->nullable();
            $table->string('weight')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('shipping_rate_id')->references('id')->on('shipping_rates');
            $table->foreign('currency_id')->references('id')->on('currencies');
        });

        Schema::create('shipping_state_exclusions', function (Blueprint $table): void {
            $table->unsignedBigInteger('shipping_zone_country_id');
            $table->unsignedBigInteger('state_id');
            $table->dateTime('created_at')->useCurrent();

            $table->primary(['shipping_zone_country_id', 'state_id']);
            $table->foreign('shipping_zone_country_id')->references('id')->on('shipping_zone_country');
            $table->foreign('state_id')->references('id')->on('states');
        });

        Schema::create('packagings', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36)->unique('uuid');
            $table->string('name');
            $table->foreignId('store_id')->constrained('stores');
            $table->tinyInteger('type');
            $table->decimal('length', 10);
            $table->decimal('width', 10);
            $table->decimal('height', 10);
            $table->tinyInteger('unit');
            $table->decimal('empty_weight', 10);
            $table->tinyInteger('empty_unit');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'is_default'], 'idx_packaging_store_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packagings');
        Schema::dropIfExists('shipping_state_exclusions');
        Schema::dropIfExists('shipping_zone_rates');
        Schema::dropIfExists('shipping_zone_country');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('shipping_rates');
    }
};
