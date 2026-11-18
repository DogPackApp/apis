<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facet_translations', function (Blueprint $table): void {
            $table->bigInteger('entity_id');
            $table->string('entity_type', 100)->default('title');
            $table->string('locale', 10);
            $table->text('text');

            $table->primary(['entity_id', 'entity_type', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facet_translations');
    }
};
