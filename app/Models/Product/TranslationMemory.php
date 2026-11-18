<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per (source text, source language, target language) already paid for
 * at Google Translate. Written only by TranslationMemoryJob.
 */
class TranslationMemory extends Model
{
    protected $table = 'translation_memory';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'source_hash',
        'from_language',
        'to_language',
        'translated_text',
    ];
}
