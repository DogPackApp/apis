<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('item_id')->constrained('items');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('attribute_options', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('attribute_id')->constrained('attributes');
            $table->string('attribute_value');
            $table->timestamps();

            $table->unique(['attribute_id', 'attribute_value']);
        });

        Schema::create('item_variants', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('item_id')->constrained('items');
            $table->string('sku')->nullable();
            $table->string('gtin')->nullable();
            $table->integer('domestic_price')->nullable();
            $table->integer('global_price')->nullable();
            $table->integer('stock_quantity')->nullable();
            $table->string('weight')->nullable();
            $table->boolean('is_visible')->nullable()->default(false);
            $table->boolean('allow_overselling')->default(false);
            $table->integer('order');
            $table->string('source_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('variant_combinations', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('item_variant_id')->constrained('item_variants');
            $table->foreignId('attribute_option_id')->constrained('attribute_options');
            $table->timestamps();
        });

        Schema::create('vary_by', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('item_id')->constrained('items');
            $table->tinyInteger('sku')->default(0);
            $table->tinyInteger('quantity')->default(0);
            $table->tinyInteger('price')->default(0);
            $table->tinyInteger('weight')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vary_by');
        Schema::dropIfExists('variant_combinations');
        Schema::dropIfExists('item_variants');
        Schema::dropIfExists('attribute_options');
        Schema::dropIfExists('attributes');
    }
};
