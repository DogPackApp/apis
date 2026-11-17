<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_statuses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('seller_id')->unique()->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->tinyInteger('is_product')->default(0);
            $table->tinyInteger('is_shipping')->default(0);
            $table->tinyInteger('is_store_setting')->default(0);
            $table->tinyInteger('is_finance')->default(0);
            $table->tinyInteger('is_subscribe')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_statuses');
    }
};
