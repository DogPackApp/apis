<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone', 100)->nullable();
            $table->string('password');
            $table->string('google_id')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->tinyInteger('twofa_enabled')->default(0);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
