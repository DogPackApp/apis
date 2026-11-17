<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_prices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('item_id')->unique()->constrained('items')->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->integer('domestic_price');
            $table->integer('global_price')->nullable();
            $table->boolean('is_global_pricing')->default(false);
            $table->integer('stock_quantity')->nullable();
            $table->integer('weight')->nullable();
            $table->tinyInteger('unit')->nullable();
            $table->boolean('allow_overselling')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_prices');
    }
};
