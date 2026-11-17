<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_section', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('section_id')->constrained('sections');
            $table->integer('order')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_section');
    }
};
