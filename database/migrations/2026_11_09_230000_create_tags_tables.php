<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('seller_id')->constrained('sellers');
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
        });

        Schema::create('item_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->unsignedBigInteger('tags_id');
            $table->index('tags_id', 'item_tags_tag_id_foreign');

            $table->foreign('tags_id')->references('id')->on('tags');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_tags');
        Schema::dropIfExists('tags');
    }
};
