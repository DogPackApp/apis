<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('item_value_translations', function (Blueprint $table): void {
            $table->bigInteger('item_id');
            $table->string('type', 50)->default('description');
            $table->string('locale', 10);
            $table->text('value');

            $table->primary(['item_id', 'type', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_value_translations');
    }
};
