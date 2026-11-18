<?php

namespace App\Observers\Product;

use App\Jobs\Product\ClassifyItemToFacetsJob;
use App\Jobs\Product\ItemTranslationJob;
use App\Jobs\Product\SyncProductToIndexJob;
use App\Models\Product\Item;
use App\Models\Product\ItemValueTranslation;
use Illuminate\Support\Facades\Log;
use Throwable;

class ItemObserver
{
    /**
     * Handle the Item "saved" event (fires on both create and update).
     * Facet classification runs first so newly captured (or now unused)
     * mappings are reflected by the index sync that follows it.
     */
    public function saved(Item $item): void
    {
        $this->dispatchChain($item);
        $this->clearStaleTranslations($item);
    }

    /**
     * Handle the Item "deleted" event (soft delete).
     */
    public function deleted(Item $item): void
    {
        $this->dispatchChain($item, active: false);
    }

    /**
     * Handle the Item "restored" event (soft delete restore).
     */
    public function restored(Item $item): void
    {
        $this->dispatchChain($item);
    }

    /**
     * Handle the Item "forceDeleted" event.
     */
    public function forceDeleted(Item $item): void
    {
        $this->dispatchChain($item, active: false);
    }

    /**
     * Chains facet classification (or mapping removal) ahead of the
     * Elasticsearch sync, so the synced document always reflects the
     * item's latest facet mappings.
     */
    private function dispatchChain(Item $item, bool $active = true): void
    {
        if ($item->id === null) {
            return;
        }

        $itemId = (int) $item->id;

        $classifyAction = $active
            ? ClassifyItemToFacetsJob::ACTION_UPSERT
            : ClassifyItemToFacetsJob::ACTION_DELETE;

        $syncAction = $active
            ? SyncProductToIndexJob::ACTION_UPSERT
            : SyncProductToIndexJob::ACTION_DELETE;

        ClassifyItemToFacetsJob::dispatch($itemId, $classifyAction)
            ->chain([new SyncProductToIndexJob($itemId, $syncAction)]);
    }

    /**
     * Wipe cached translations once the source title/description changes,
     * so stale translated values aren't served until they're regenerated.
     */
    private function clearStaleTranslations(Item $item): void
    {
        try {
            $toDelete = [];
            if ($item->wasChanged(['title'])) {
                $toDelete[] = ItemValueTranslation::TYPE_TITLE;
            }

            if ($item->wasChanged(['description'])) {
                $toDelete[] = ItemValueTranslation::TYPE_DESCRIPTION;
            }

            if ($toDelete !== []) {
                ItemTranslationJob::dispatch([
                    'item_id' => $item->id,
                    'type' => $toDelete,
                ], ItemTranslationJob::ACTION_DELETE);
            }
        } catch (Throwable $e) {
            Log::error("Failed to clear stale translations for item {$item->id}", [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
