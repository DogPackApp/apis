<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permanent store for Google Translate results. GoogleTranslateService reads
     * it after a Redis miss and fills it through TranslationMemoryJob, so a
     * string is only ever billed once per language pair, even if Redis evicts it.
     */
    public function up(): void
    {
        Schema::create('translation_memory', function (Blueprint $table): void {
            $table->char('source_hash', 32);
            $table->string('from_language', 10)->default('auto');
            $table->string('to_language', 10);
            $table->text('translated_text');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['source_hash', 'from_language', 'to_language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_memory');
    }
};
