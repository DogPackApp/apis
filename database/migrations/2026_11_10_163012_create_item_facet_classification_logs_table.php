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
        Schema::create('item_facet_classification_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->json('prompts');
            $table->json('usage')->nullable();
            $table->decimal('cost', 12, 8)->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->json('response')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_facet_classification_logs');
    }
};
