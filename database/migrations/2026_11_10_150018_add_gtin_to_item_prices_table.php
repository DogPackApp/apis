<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_prices', function (Blueprint $table): void {
            $table->string('gtin')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('item_prices', function (Blueprint $table): void {
            $table->dropColumn('gtin');
        });
    }
};
