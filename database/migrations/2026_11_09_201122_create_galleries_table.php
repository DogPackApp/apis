<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('store_id')->constrained('stores');
            $table->string('url');
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galleries');
    }
};
