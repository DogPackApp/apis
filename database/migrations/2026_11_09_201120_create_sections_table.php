<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('store_id')->constrained('stores');
            $table->string('name');
            $table->string('slug');
            $table->tinyInteger('is_visible');
            $table->timestamps();
            $table->tinyInteger('order');

            $table->index(['store_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
