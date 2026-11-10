<?php

namespace App\Jobs\Product;

use App\Services\Product\ItemFacetClassificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClassifyItemToFacetsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ACTION_UPSERT = 'upsert';

    public const ACTION_DELETE = 'delete';

    /**
     * Seconds to hold the unique lock so rapid successive saves (e.g. product update
     * saving item twice) collapse into a single queued job.
     */
    public int $uniqueFor = 30;

    public function __construct(
        private readonly int $itemId,
        private readonly string $action = self::ACTION_UPSERT,
    ) {
        $this->onQueue(config('queue.medium'));
    }

    public function uniqueId(): string
    {
        return $this->itemId.':'.$this->action;
    }

    public function handle(ItemFacetClassificationService $classificationService): void
    {
        try {
            switch ($this->action) {
                case self::ACTION_UPSERT:
                    $item = ItemFacetClassificationService::buildItemQueryForClassification()->find($this->itemId);
                    if ($item?->isActive()) {
                        $summary = $classificationService->classifyAndSync($item);
                        Log::info('ClassifyItemToFacetsJob summary for item '.$this->itemId, [
                            'summary' => $summary,
                        ]);
                    } else {
                        $classificationService->deleteMappings($this->itemId);
                    }
                    break;
                case self::ACTION_DELETE:
                    $classificationService->deleteMappings($this->itemId);
                    break;
                default:
                    throw new \InvalidArgumentException('Invalid action: '.$this->action);
            }
        } catch (Throwable $e) {
            Log::error('Error classifying item to facets: '.$e->getMessage(),
                ['item_id' => $this->itemId, 'action' => $this->action, 'trace' => $e->getTraceAsString()]);
        }
    }
}
