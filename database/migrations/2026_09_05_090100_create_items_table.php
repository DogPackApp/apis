<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedBigInteger('policy_id')->nullable();
            $table->tinyInteger('type')->default(1);
            $table->string('title');
            $table->string('slug', 80)->nullable()->index();
            $table->text('description')->nullable();
            $table->tinyInteger('status')->default(2);
            $table->string('origin')->nullable();
            $table->string('source_id')->nullable()->unique();
            $table->string('media_url')->nullable();
            $table->boolean('manually_deactivated')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'category_id']);
            $table->index(['store_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
