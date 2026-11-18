<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zone_rate_items', function (Blueprint $table): void {
            $table->bigInteger('shipping_zone_rate_id');
            $table->bigInteger('item_id');
            $table->dateTime('created_at')->useCurrent();

            $table->primary(['shipping_zone_rate_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_rate_items');
    }
};
