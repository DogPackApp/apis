<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('store_id')->constrained('stores');
            $table->string('name');
            $table->tinyInteger('is_return')->default(0);
            $table->tinyInteger('is_exchange')->default(0);
            $table->tinyInteger('is_default')->default(0);
            $table->string('custom_text', 1000)->nullable();
            $table->timestamps();
            $table->integer('time')->nullable();

            $table->index(['store_id', 'is_default'], 'idx_store_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};
