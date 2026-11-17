<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories_translation', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('categories_id')->index();
            $table->unsignedBigInteger('language_id')->nullable();
            $table->string('lang_type', 10);
            $table->string('title')->nullable();
            $table->string('type');

            $table->index(['categories_id', 'lang_type', 'type'], 'categories_translation_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories_translation');
    }
};
