<?php

namespace App\Console\Commands\Product\Elasticsearch;

use App\Services\Elasticsearch\ProductIndexService;
use Illuminate\Console\Command;

class ReindexProductsCommand extends Command
{
    protected $signature = 'elasticsearch:products:reindex {--index= : Reuse an existing target index instead of creating one} {--no-alias : Do not swap the products alias after indexing}';

    protected $description = 'Fully re-index active marketplace products from the existing MySQL database.';

    public function handle(ProductIndexService $indexService): int
    {
        $this->info('Starting full product re-index from MySQL into Elasticsearch...');

        $result = $indexService->reindex(
            $this->option('index') ?: null,
            ! $this->option('no-alias')
        );

        $this->info("Indexed {$result['indexed']} products into {$result['index']}.");

        if ($result['failed'] > 0) {
            $this->warn("Bulk indexing reported {$result['failed']} failed documents.");
        }

        if ($result['alias']) {
            $this->line("Alias '{$result['alias']}' now points to {$result['index']}.");
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
