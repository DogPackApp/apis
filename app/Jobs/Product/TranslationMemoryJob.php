<?php

namespace App\Jobs\Product;

use App\Models\Product\TranslationMemory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Saves freshly translated strings into translation_memory.
 *
 * Dispatched by GoogleTranslateService after every successful Google call so the
 * request never waits on the insert. insertOrIgnore on the composite primary key
 * makes it safe to run twice or for two requests to translate the same string.
 */
class TranslationMemoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<int, array{source_hash:string, from_language:string, to_language:string, translated_text:string, created_at:string}>  $entries
     */
    protected array $entries;

    public function __construct(array $entries)
    {
        $this->onQueue(config('queue.translations'));
        $this->entries = $entries;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        try {
            foreach (array_chunk($this->entries, 200) as $chunk) {
                TranslationMemory::insertOrIgnore($chunk);
            }
        } catch (Throwable $e) {
            Log::error('Failed to store translation memory: '.$e->getMessage(), [
                'entries_count' => count($this->entries),
            ]);

            throw $e;
        }
    }
}
