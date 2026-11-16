<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_field_ownerships')) {
            return;
        }

        Schema::create('product_field_ownerships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->string('field_name');
            $table->string('ownership');
            $table->timestamps();

            $table->foreign('item_id')
                ->references('id')
                ->on('items')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->unique(['item_id', 'field_name'], 'idx_unique_item_id_field_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_field_ownerships');
    }
};
