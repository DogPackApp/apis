<?php

namespace App\Jobs\Product;

use App\Models\Product\FacetTranslation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class FacetTranslationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 20;

    /**
     * @param  list<array{entity_id: int, entity_type: string, locale: string, text: string}>  $entries
     */
    public function __construct(protected array $entries)
    {
        $this->onQueue(config('queue.translations'));
    }

    public function uniqueId(): string
    {
        return md5(json_encode($this->entries));
    }

    public function handle(): void
    {
        try {
            FacetTranslation::query()->insertOrIgnore($this->entries);
        } catch (Throwable $e) {
            Log::error('Failed to store facet translations: '.$e->getMessage(), [
                'entries' => $this->entries,
            ]);
        }
    }
}
