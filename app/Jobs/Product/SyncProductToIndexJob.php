<?php

namespace App\Jobs\Product;

use App\Models\Product\Item;
use App\Notifications\SlackNotifier;
use App\Notifications\SyncProductToIndexFailed;
use App\Services\Elasticsearch\ProductIndexService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncProductToIndexJob implements ShouldBeUnique, ShouldQueue
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
        $this->onQueue(config('queue.elasticsearch'));
    }

    public function uniqueId(): string
    {
        return $this->itemId.':'.$this->action;
    }

    public function handle(ProductIndexService $indexService): void
    {
        try {
            if ($this->action === self::ACTION_DELETE) {
                $indexService->deleteDocument($this->itemId);

                return;
            }

            // Reload from the primary connection so we reflect the just-written state
            // (avoids replica lag) and pick up soft-delete / status changes.
            $item = Item::query()
                ->with($indexService->documentRelations())
                ->find($this->itemId);

            // Missing (soft-deleted or status = DELETED via global scope), inactive,
            // or manually deactivated items must not remain searchable.
            if ($item === null || ! $indexService->isIndexable($item)) {
                $indexService->deleteDocument($this->itemId);
                Log::info('SyncProductToIndexJob deleted document for item '.$this->itemId);

                return;
            }

            $indexService->indexItem($item);
            Log::info('SyncProductToIndexJob indexed document for item '.$this->itemId);
        } catch (Throwable $e) {
            Log::error('SyncProductToIndexJob failed for item '.$this->itemId.': '.$e->getMessage());

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
            (new SlackNotifier)->notify(new SyncProductToIndexFailed(
                itemId: $this->itemId,
                action: $this->action,
                exception: $exception,
                timestamp: Carbon::now('America/Toronto')->format('Y-m-d H:i:s'),
            ));
        } catch (Throwable $e) {
            Log::error('Failed to send Slack product sync failure notification', ['error' => $e->getMessage()]);
        }
    }
}
