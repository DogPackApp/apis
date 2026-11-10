<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_facet_mappings', function (Blueprint $table): void {
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('facet_value_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(
                ['item_id', 'facet_value_id'],
                'item_facet_mappings_primary'
            );

            $table->index(
                'facet_value_id',
                'item_facet_mappings_facet_value_id_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_facet_mappings');
    }
};
