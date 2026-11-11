<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribe_save_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('dogpack_first_order_percent');
            $table->json('allowed_seller_percents');
            $table->json('allowed_frequencies_days');
            $table->json('threshold_percent')->nullable();
            $table->json('max_discount_amount')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribe_save_settings');
    }
};
