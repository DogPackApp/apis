<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->unsignedInteger('country_id')->nullable()->after('seller_id');
            $table->string('stripe_account_id', 50)->nullable();
            $table->string('stripe_status')->nullable();

            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropForeign(['country_id']);
            $table->dropColumn(['country_id', 'stripe_account_id', 'stripe_status']);
        });
    }
};
