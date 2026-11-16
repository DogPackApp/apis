<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_access_tokens', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('shop_domain_name', 255)->unique();
            $table->unsignedBigInteger('store_id')->unique();
            $table->string('access_token', 1024);
            $table->timestamps();

            $table->index('shop_domain_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_access_tokens');
    }
};
