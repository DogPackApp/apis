<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_fulfillments', function (Blueprint $table): void {
            $table->string('tracking_url', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('manual_fulfillments', function (Blueprint $table): void {
            $table->dropColumn('tracking_url');
        });
    }
};
