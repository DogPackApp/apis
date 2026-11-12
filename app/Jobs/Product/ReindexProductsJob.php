<?php

namespace App\Jobs\Product;

use App\Notifications\ReindexProductsNotification;
use App\Notifications\SlackNotifier;
use App\Services\Elasticsearch\ProductIndexService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Nightly full rebuild of the products index from MySQL.
 * Acts as the safety net behind the real-time ItemObserver: it reconciles any
 * drift (missed updates, deletes) by building a fresh index and swapping the alias,
 * then pruning the now-orphaned old indices.
 */
class ReindexProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 1200;

    public function __construct()
    {
        $this->onConnection(config('queue.elasticsearch'));
        $this->onQueue(config('queue.elasticsearch'));
    }

    public function handle(ProductIndexService $indexService): void
    {
        try {
            $result = $indexService->reindex();
            $pruned = $indexService->pruneOldIndices($result['index']);

            Log::info(sprintf(
                'ReindexProductsJob: indexed %d (failed %d) into %s; pruned %d old index(es).',
                $result['indexed'],
                $result['failed'],
                $result['index'],
                $pruned
            ));

            $this->notifySlack(success: true, result: $result, pruned: $pruned);
        } catch (Throwable $e) {
            Log::error('ReindexProductsJob failed: '.$e->getMessage());
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->notifySlack(success: false, exception: $exception);
    }

    /**
     * @param  array{indexed?: int, failed?: int, index?: string}|null  $result
     */
    private function notifySlack(
        bool $success,
        ?array $result = null,
        ?int $pruned = null,
        ?Throwable $exception = null,
    ): void {
        try {
            (new SlackNotifier)->notify(new ReindexProductsNotification(
                success: $success,
                result: $result,
                pruned: $pruned,
                exception: $exception,
                timestamp: Carbon::now('America/Toronto')->format('Y-m-d H:i:s'),
            ));
        } catch (Throwable $e) {
            Log::error('Failed to send Slack reindex notification', ['error' => $e->getMessage()]);
        }
    }
}
