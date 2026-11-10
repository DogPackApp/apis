<?php

namespace App\Jobs\Product;

use App\Models\Product\ItemValueTranslation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ItemTranslationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const ACTION_STORE = 'store';

    const ACTION_DELETE = 'delete';

    protected array $entries;

    protected string $action;

    /**
     * Seconds to hold the unique lock so rapid successive saves (e.g. product update
     * saving item twice) collapse into a single queued job.
     */
    public int $uniqueFor = 20;

    public function __construct(array $entries, string $action)
    {
        $this->onQueue(config(('queue.translations')));
        $this->entries = $entries;
        $this->action = $action;
    }

    public function uniqueId(): string
    {
        return md5(json_encode($this->entries)).':'.$this->action;
    }

    public function handle(): void
    {
        try {
            switch ($this->action) {
                case self::ACTION_STORE:
                    ItemValueTranslation::insertOrIgnore($this->entries);
                    break;
                case self::ACTION_DELETE:
                    $itemId = $this->entries['item_id'] ?? null;
                    $type = $this->entries['type'] ?? null;
                    if (empty($itemId) || empty($type)) {
                        throw new \InvalidArgumentException('Invalid entries');
                    }
                    ItemValueTranslation::where('item_id', $itemId)->whereIn('type', $type)->delete();
                    break;
                default:
                    throw new \InvalidArgumentException('Invalid action: '.$this->action);
            }
        } catch (\Exception $e) {
            Log::error('Failed to '.$this->action.' items: '.$e->getMessage(), [
                'entries' => $this->entries,
                'action' => $this->action,
            ]);
        }
    }
}
