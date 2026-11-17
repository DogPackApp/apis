<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_otps', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->integer('otp');
            $table->tinyInteger('is_active')->default(0);
            $table->string('login_type')->default('seller');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_otps');
    }
};
