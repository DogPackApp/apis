<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->string('short_name', 10);
            $table->tinyInteger('is_front')->default(1);
            $table->tinyInteger('is_app')->default(1);
            $table->tinyInteger('is_admin')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
