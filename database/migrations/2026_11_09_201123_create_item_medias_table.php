<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_medias', function (Blueprint $table): void {
            $table->id();
            $table->char('uuid', 36);
            $table->foreignId('item_id')->constrained('items');
            $table->unsignedBigInteger('item_variant_id')->nullable()->index();
            $table->foreignId('gallery_id')->nullable()->constrained('galleries');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_medias');
    }
};
