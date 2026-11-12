<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_subscribe_save_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->unique()->constrained('items')->cascadeOnDelete();
            $table->string('admin_override', 16)->nullable();
            $table->boolean('seller_disabled')->default(false);
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_subscribe_save_settings');
    }
};
