<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_products', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->unsignedInteger('_id')->nullable();
            $table->uuid('batch_id')->index();
            $table->string('product_id')->nullable();
            $table->string('title')->nullable();
            $table->string('type')->nullable();
            $table->text('description')->nullable();
            $table->text('media_urls')->nullable();
            $table->text('tags')->nullable();
            $table->text('sections')->nullable();
            $table->string('status')->nullable();
            $table->string('country_of_origin')->nullable();
            $table->string('price')->nullable();
            $table->string('quantity')->nullable();
            $table->string('sku')->nullable();
            $table->string('weight')->nullable();
            $table->string('unit')->nullable();
            $table->string('continue_selling_when_out_of_stock')->nullable();
            $table->json('media_uuids')->nullable();
            $table->json('errors')->nullable();
            $table->boolean('is_validated')->default(false);
            $table->boolean('is_imported')->default(false);
            $table->timestamps();
        });

        Schema::create('import_product_variants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->unsignedInteger('_id')->nullable();
            $table->foreignId('import_product_id')->constrained('import_products')->cascadeOnDelete();
            $table->string('option1_name')->nullable();
            $table->string('option1_value')->nullable();
            $table->string('option2_name')->nullable();
            $table->string('option2_value')->nullable();
            $table->string('option3_name')->nullable();
            $table->string('option3_value')->nullable();
            $table->string('price')->nullable();
            $table->string('quantity')->nullable();
            $table->string('country_of_origin')->nullable();
            $table->string('sku')->nullable();
            $table->string('weight')->nullable();
            $table->string('unit')->nullable();
            $table->boolean('continue_selling_when_out_of_stock')->default(false);
            $table->string('hide_variant')->nullable();
            $table->text('image')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();
        });

        Schema::create('item_csvs', function (Blueprint $table): void {
            $table->id();
            $table->string('product_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->index();
            $table->unsignedBigInteger('item_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_csvs');
        Schema::dropIfExists('import_product_variants');
        Schema::dropIfExists('import_products');
    }
};
