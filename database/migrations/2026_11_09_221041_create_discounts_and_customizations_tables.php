<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('item_id')->constrained('items');
            $table->integer('value')->default(0);
            $table->boolean('is_limited')->default(false);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();
        });

        Schema::create('customizations', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('item_id')->constrained('items');
            $table->tinyInteger('description_enabled')->nullable();
            $table->string('description')->nullable();
            $table->integer('limit')->nullable();
            $table->tinyInteger('attach_file')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customizations');
        Schema::dropIfExists('discounts');
    }
};
