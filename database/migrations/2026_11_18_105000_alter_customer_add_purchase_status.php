<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('purchase_status', 100)->nullable();
            $table->index('user_id');
            $table->index('email');
        });

        DB::table('customers')
            ->whereIn('store_order_id', function ($query): void {
                $query->select('id')
                    ->from('store_orders')
                    ->whereNotNull('dispute_status')
                    ->where('dispute_status', '!=', '');
            })
            ->update(['purchase_status' => 'blocked']);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['email']);
            $table->dropColumn('purchase_status');
        });
    }
};
