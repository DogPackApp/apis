<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->unsignedInteger('currency_id')->nullable();
            $table->unsignedInteger('language_id')->nullable();
        });

        Schema::create('stores_info', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('vat_number')->nullable();
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
            $table->unique('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores_info');

        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn(['currency_id', 'language_id']);
        });
    }
};
