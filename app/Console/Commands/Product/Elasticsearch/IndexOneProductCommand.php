<?php

namespace App\Console\Commands\Product\Elasticsearch;

use App\Models\Product\Item;
use App\Services\Elasticsearch\ProductIndexService;
use Illuminate\Console\Command;

class IndexOneProductCommand extends Command
{
    protected $signature = 'elasticsearch:products:index-one {id : Existing items.id value}';

    protected $description = 'Sync one product document with the live Elasticsearch index (indexes if active, otherwise removes it).';

    public function handle(ProductIndexService $indexService): int
    {
        $id = (int) $this->argument('id');

        $item = Item::on('mysql_reader')
            ->withoutGlobalScope('excludeDeletedItems')
            ->withTrashed()
            ->with($indexService->documentRelations())
            ->find($id);

        if ($item === null || ! $indexService->isIndexable($item)) {
            $indexService->deleteDocument($id);
            $this->info("Item {$id} is not indexable; removed from index (if present).");

            return self::SUCCESS;
        }

        $result = $indexService->indexItem($item);

        $this->info("Indexed product {$item->id}. Failed documents: {$result['failed']}");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
