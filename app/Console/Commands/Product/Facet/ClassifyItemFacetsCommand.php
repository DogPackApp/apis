<?php

namespace App\Console\Commands\Product\Facet;

use App\Models\Product\Item;
use App\Services\Product\CategoryService;
use App\Services\Product\ItemFacetClassificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClassifyItemFacetsCommand extends Command
{
    protected $signature = 'marketplace:classify-item-facets
                            {--chunk=25 : Number of items to process per chunk}
                            {--item= : Classify a single item by ID}
                            {--store= : Store the classification results in a specific store ID}
                            {--category= : Limit to items in a specific category ID}
                            {--limit= : Maximum number of items to process}
                            {--include-inactive : Include non-active items}
                            {--force : Reclassify items whose content has not changed since the last run}';

    protected $description = 'Classify marketplace items into category facet values via OpenAI, processing in chunks';

    public function handle(ItemFacetClassificationService $classificationService, CategoryService $categoryService): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $force = (bool) $this->option('force');
        $limit = $this->option('limit') !== null
            ? max(1, (int) $this->option('limit'))
            : null;

        $query = ItemFacetClassificationService::buildItemQueryForClassification();

        if ($itemId = $this->option('item')) {
            $query->where('id', (int) $itemId);
        }

        if ($storeId = $this->option('store')) {
            $query->where('store_id', (int) $storeId);
        }

        if ($categoryId = $this->option('category')) {
            $childCategories = $categoryService->getAllChildIds((int) $categoryId);
            $query->whereIn('category_id', array_merge([(int) $categoryId], $childCategories));
        }

        if (! $this->option('include-inactive')) {
            $query->where('status', Item::STATUS[Item::STATUS_ACTIVE]);
        }

        $total = (clone $query)->count();

        if ($limit !== null) {
            $total = min($total, $limit);
        }

        if ($total === 0) {
            $this->info('No items to classify.');

            return self::SUCCESS;
        }

        $this->info("Classifying {$total} item(s) in chunks of {$chunkSize}...");
        Log::info('[ClassifyItemFacets] Starting', [
            'total' => $total,
            'chunk' => $chunkSize,
            'item' => $this->option('item'),
            'category' => $this->option('category'),
        ]);

        $processed = 0;
        $mapped = 0;
        $skipped = 0;
        $unchanged = 0;
        $failed = 0;
        $requests = 0;
        $usage = [
            'input_tokens' => 0,
            'cached_input_tokens' => 0,
            'output_tokens' => 0,
        ];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('id')->chunkById($chunkSize, function ($items) use (
            $classificationService,
            $limit,
            $force,
            &$processed,
            &$mapped,
            &$skipped,
            &$unchanged,
            &$failed,
            &$requests,
            &$usage,
            $bar
        ) {
            foreach ($items as $item) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }

                try {
                    $result = $classificationService->classifyAndSync(
                        $item,
                        $force
                    );

                    if ($result === null) {
                        $skipped++;
                    } elseif ($result['skipped'] ?? false) {
                        $unchanged++;
                    } else {
                        $mapped += (int) ($result['mapped_count'] ?? 0);

                        // Usage is present only when the model was called.
                        if (is_array($result['usage'] ?? null)) {
                            $requests++;

                            foreach (array_keys($usage) as $token) {
                                $usage[$token] +=
                                    (int) ($result['usage'][$token] ?? 0);
                            }
                        }
                    }
                } catch (Throwable $exception) {
                    $failed++;

                    Log::error('[ClassifyItemFacets] Failed to classify item', [
                        'item_id' => $item->id,
                        'exception' => $exception->getMessage(),
                    ]);

                    $this->newLine();
                    $this->error(
                        "Item {$item->id} failed: {$exception->getMessage()}"
                    );
                }

                $processed++;
                $bar->advance();
            }

            return true;
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(
            "Done. Processed: {$processed}, mappings written: {$mapped}, "
            ."unchanged: {$unchanged}, skipped: {$skipped}, failed: {$failed}."
        );

        $cost = $classificationService->estimateUsageCost($usage);

        $this->reportUsage($requests, $usage, $cost);

        Log::info('[ClassifyItemFacets] Completed', [
            'processed' => $processed,
            'mapped' => $mapped,
            'unchanged' => $unchanged,
            'skipped' => $skipped,
            'failed' => $failed,
            'requests' => $requests,
            'usage' => $usage,
            'estimated_cost_usd' => round($cost, 6),
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Prints what the run spent at OpenAI. Items served from the fingerprint
     * cache never reach the API, so they are absent from these totals.
     */
    private function reportUsage(int $requests, array $usage, float $cost): void
    {
        if ($requests === 0) {
            $this->info('No OpenAI requests were made, so nothing was spent.');

            return;
        }

        $totalTokens = $usage['input_tokens'] + $usage['output_tokens'];

        $this->info(sprintf(
            'Tokens: %s total across %s request(s) - %s input (%s cached), %s output.',
            number_format($totalTokens),
            number_format($requests),
            number_format($usage['input_tokens']),
            number_format($usage['cached_input_tokens']),
            number_format($usage['output_tokens'])
        ));

        $this->info(sprintf(
            'Estimated cost: $%s total, $%s per classified item, $%s per 1,000.',
            number_format($cost, 4),
            number_format($cost / $requests, 6),
            number_format(($cost / $requests) * 1000, 2)
        ));
    }
}
