<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_countries', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('short_name', 10);
            $table->string('name');
            $table->string('currency_code', 10)->nullable();
            $table->string('currency_name')->nullable();
            $table->string('currency_symbol', 10)->nullable();
            $table->tinyInteger('is_display')->default(0);
            $table->tinyInteger('is_display_front')->default(0);
            $table->tinyInteger('is_display_mobile')->default(0);
            $table->integer('order')->default(999);
        });

        if (! Schema::hasTable('seller_countries')) {
            Schema::create('seller_countries', function (Blueprint $table): void {
                $table->unsignedInteger('id')->primary();
                $table->string('short_name', 10)->nullable();
                $table->string('name')->nullable();
                $table->string('currency_code', 10)->nullable();
                $table->string('currency_name')->nullable();
                $table->string('currency_symbol', 10)->nullable();
            });
        }

        if (! Schema::hasTable('countries_new')) {
            Schema::create('countries_new', function (Blueprint $table): void {
                $table->unsignedInteger('id')->primary();
                $table->string('short_name', 10)->nullable();
                $table->string('name')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('countries_new');
        Schema::dropIfExists('seller_countries');
        Schema::dropIfExists('marketplace_countries');
    }
};
