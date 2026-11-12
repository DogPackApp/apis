<?php

namespace App\Console\Commands\Product\Elasticsearch;

use App\Services\Elasticsearch\ProductIndexService;
use Illuminate\Console\Command;

class CreateProductIndexCommand extends Command
{
    protected $signature = 'elasticsearch:products:create-index {--name= : Explicit index name} {--no-alias : Do not point the products alias at the new index}';

    protected $description = 'Create the Elasticsearch product index with mappings, analyzers, and the stored search template.';

    public function handle(ProductIndexService $indexService): int
    {
        $index = $indexService->createIndex($this->option('name') ?: null);

        if (! $this->option('no-alias')) {
            $indexService->swapAlias($index);
        }

        $this->info("Created product index: {$index}");

        if (! $this->option('no-alias')) {
            $this->line("Alias '{$indexService->alias()}' now points to {$index}");
        }

        return self::SUCCESS;
    }
}
