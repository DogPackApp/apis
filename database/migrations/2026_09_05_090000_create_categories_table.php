<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('name');
            $table->string('images')->nullable();
            $table->string('ht_code')->nullable();
            $table->string('slug');
            $table->string('order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('images_thumb')->nullable();
            $table->string('banner_image')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
