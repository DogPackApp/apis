<?php

namespace App\Console\Commands\Product\Elasticsearch;

use App\Services\Elasticsearch\ProductIndexService;
use Illuminate\Console\Command;

class DeleteProductIndexCommand extends Command
{
    protected $signature = 'elasticsearch:products:delete-index {index : Physical index name to delete} {--force : Delete without confirmation}';

    protected $description = 'Delete a physical Elasticsearch product index.';

    public function handle(ProductIndexService $indexService): int
    {
        $index = (string) $this->argument('index');

        if (! $this->option('force') && ! $this->confirm("Delete Elasticsearch index '{$index}'?")) {
            $this->line('Cancelled.');

            return self::SUCCESS;
        }

        $indexService->deleteIndex($index);
        $this->info("Deleted index: {$index}");

        return self::SUCCESS;
    }
}
