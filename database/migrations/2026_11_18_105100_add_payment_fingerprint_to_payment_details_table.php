<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_details', function (Blueprint $table): void {
            $table->string('payment_fingerprint')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('payment_details', function (Blueprint $table): void {
            $table->dropIndex(['payment_fingerprint']);
            $table->dropColumn('payment_fingerprint');
        });
    }
};
