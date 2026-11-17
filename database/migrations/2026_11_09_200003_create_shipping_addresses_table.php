<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_addresses', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('store_id')->constrained('stores');
            $table->string('easypost_id')->nullable();
            $table->string('address');
            $table->string('street1');
            $table->string('street2')->nullable();
            $table->string('zipcode');
            $table->string('city');
            $table->string('country');
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->string('province');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_addresses');
    }
};
