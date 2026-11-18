<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->boolean('is_valid')->default(false)->after('manually_deactivated');
        });

        Schema::create('store_shopify_connections', function (Blueprint $table): void {
            $table->unsignedBigInteger('store_id')->primary();
            $table->foreign('store_id')->references('id')->on('stores');
        });

        Schema::create('category_facets', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('category_id')->nullable()->index('category_facets_category_id_idx');
            $table->integer('display_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories');
        });

        Schema::create('facet_values', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('category_facet_id')->index('facet_values_category_facet_id_idx');
            $table->integer('display_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('category_facet_id')->references('id')->on('category_facets');
        });

        Schema::create('export_products', function (Blueprint $table): void {
            $table->char('batch_id', 36);
            $table->char('product_uuid', 36)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_products');
        Schema::dropIfExists('facet_values');
        Schema::dropIfExists('category_facets');
        Schema::dropIfExists('store_shopify_connections');
        Schema::table('items', function (Blueprint $table): void {
            $table->dropColumn('is_valid');
        });
    }
};
