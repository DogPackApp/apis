<?php

namespace App\Services\Elasticsearch;

use App\Models\Product\Item;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Illuminate\Support\Collection;

class ProductIndexService
{
    public function __construct(
        private readonly Client $client,
        private readonly ProductDocumentFactory $documentFactory,
    ) {}

    public function alias(): string
    {
        return config('elasticsearch.products.alias');
    }

    public function createIndex(?string $indexName = null): string
    {
        $indexName ??= $this->newIndexName();

        $this->client->indices()->create([
            'index' => $indexName,
            'body' => ProductIndexDefinition::body(),
        ]);

        return $indexName;
    }

    public function deleteIndex(string $indexName): void
    {
        $this->client->indices()->delete(['index' => $indexName]);
    }

    public function swapAlias(string $indexName): void
    {
        $actions = [];

        foreach ($this->indicesForAlias() as $existingIndex) {
            $actions[] = ['remove' => ['index' => $existingIndex, 'alias' => $this->alias()]];
        }

        $actions[] = ['add' => ['index' => $indexName, 'alias' => $this->alias()]];

        $this->client->indices()->updateAliases([
            'body' => ['actions' => $actions],
        ]);
    }

    public function reindex(?string $indexName = null, bool $swapAlias = true): array
    {
        $indexName ??= $this->createIndex();
        $bulkSize = (int) config('elasticsearch.products.bulk_size');
        $indexed = 0;
        $failed = 0;

        $this->indexableQuery()
            ->chunkById($bulkSize, function (Collection $items) use ($indexName, &$indexed, &$failed): void {
                $result = $this->bulkIndex($indexName, $items);
                $indexed += $result['indexed'];
                $failed += $result['failed'];
            }, 'items.id', 'id');

        $this->client->indices()->refresh(['index' => $indexName]);

        if ($swapAlias) {
            $this->swapAlias($indexName);
        }

        return [
            'index' => $indexName,
            'alias' => $swapAlias ? $this->alias() : null,
            'indexed' => $indexed,
            'failed' => $failed,
        ];
    }

    /**
     * Index (upsert) a single item document into the live alias.
     * Reloads the relations required by the document factory so the document is complete.
     */
    public function indexItem(Item $item): array
    {
        $item->loadMissing($this->documentRelations());

        return $this->bulkIndex($this->alias(), new Collection([$item]));
    }

    /**
     * Remove a single item document from the live alias.
     * A 404 (document already absent) is treated as a successful no-op.
     */
    public function deleteDocument(int $id): void
    {
        try {
            $this->client->delete([
                'index' => $this->alias(),
                'id' => (string) $id,
            ]);
        } catch (ClientResponseException $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }
        }
    }

    /**
     * Delete older physical product indices matching the configured prefix, keeping the
     * two most recent (by index name). Used after a full reindex/alias swap so indices
     * do not accumulate while leaving one previous index available for rollback.
     */
    public function pruneOldIndices(string $keepIndex): int
    {
        $pattern = config('elasticsearch.products.index_prefix').'_*';

        try {
            $indices = array_keys(
                $this->client->indices()->get(['index' => $pattern])->asArray()
            );
        } catch (ClientResponseException $exception) {
            if ($exception->getCode() === 404) {
                return 0;
            }

            throw $exception;
        }

        rsort($indices);

        $keep = array_slice($indices, 0, 2);
        if (! in_array($keepIndex, $keep, true)) {
            $keep[] = $keepIndex;
        }

        $deleted = 0;
        foreach ($indices as $index) {
            if (in_array($index, $keep, true)) {
                continue;
            }

            $this->deleteIndex($index);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Whether an item should be present (searchable) in the index.
     */
    public function isIndexable(Item $item): bool
    {
        return $item->isActive();
    }

    public function bulkIndex(string $indexName, Collection $items): array
    {
        if ($items->isEmpty()) {
            return ['indexed' => 0, 'failed' => 0];
        }

        $body = [];

        foreach ($items as $item) {
            $body[] = [
                'index' => [
                    '_index' => $indexName,
                    '_id' => (string) $item->id,
                ],
            ];
            $body[] = $this->documentFactory->make($item);
        }

        $response = $this->client->bulk(['body' => $body])->asArray();
        $failed = 0;

        if (($response['errors'] ?? false) === true) {
            foreach ($response['items'] ?? [] as $operation) {
                if (isset($operation['index']['error'])) {
                    $failed++;
                }
            }
        }

        return [
            'indexed' => $items->count() - $failed,
            'failed' => $failed,
        ];
    }

    private function indexableQuery()
    {
        return Item::query()
            ->select('items.*')
            ->where('items.status', Item::STATUS[Item::STATUS_ACTIVE])
            ->whereNull('items.deleted_at')
            ->with($this->documentRelations())
            ->orderBy('items.id');
    }

    /**
     * Relations required by ProductDocumentFactory to build a complete document.
     *
     * @return array<int, string>
     */
    public function documentRelations(): array
    {
        return [
            'store:id,uuid,name,slug,country_id,is_verified',
            'category:id,uuid,parent_id,name,slug',
            'tags:id,uuid,name,slug',
            'price',
            'itemVariants.attributesOption.attribute',
            'attributes.attributeOptions',
            'medias:id,url,name,type',
            'discount',
            'unverified:item_id',
            'activeFacets:id,name,category_facet_id',
        ];
    }

    private function newIndexName(): string
    {
        return sprintf(
            '%s_%s',
            config('elasticsearch.products.index_prefix'),
            now()->format('Ymd_His')
        );
    }

    private function indicesForAlias(): array
    {
        try {
            $response = $this->client->indices()->getAlias(['name' => $this->alias()])->asArray();
        } catch (ClientResponseException $exception) {
            if ($exception->getCode() === 404) {
                return [];
            }

            throw $exception;
        }

        return array_keys($response);
    }
}
