<?php

namespace App\Jobs\Product;

use App\Models\Product\ImportProduct;
use App\Models\Store\Store;
use App\Services\Import\ImportService;
use App\Services\Product\ProductService;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportProcessProductJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $customBatchId;

    protected int|string $productId;

    protected int|string $storeId;

    public function __construct(string $batchId, int|string $productId, int|string $storeId)
    {
        $this->customBatchId = $batchId;
        $this->productId = $productId;
        $this->storeId = $storeId;
    }

    public function handle(): void
    {
        try {
            if ($this->batch()?->cancelled()) {
                Log::info('ImportProcessProductJob: batch cancelled', ['store_id' => $this->storeId, 'batch_id' => $this->customBatchId, 'product_id' => $this->productId]);

                return;
            }
            $store = $this->getStore($this->storeId);
            if (! $store) {
                Log::info('ImportProcessProductJob: store not found', ['store_id' => $this->storeId, 'batch_id' => $this->customBatchId, 'product_id' => $this->productId]);

                return;
            }

            $product = ImportProduct::where('id', $this->productId)
                ->where('batch_id', $this->customBatchId)
                ->whereNull('errors')
                ->where('is_validated', 1)
                ->withCount('variants')
                ->with(['variants'])
                ->first();

            if (! $product) {
                Log::info('ImportProcessProductJob: product not found', ['store_id' => $this->storeId, 'batch_id' => $this->customBatchId, 'product_id' => $this->productId]);

                return;
            }

            $importService = new ImportService(resolve(ProductService::class));
            $importService->setStore($store);

            if ($product->variants->count() > 0) {
                $importService->createProductVariants($product);
            } else {
                $importService->createProduct($product);
            }

            $product->is_imported = 1;
            Log::info('ImportProcessProductJob: imported successfully', [
                'store_id' => $this->storeId,
                'batch_id' => $this->customBatchId,
                'product_id' => $this->productId,
            ]);
            $product->save();
        } catch (Exception $exception) {
            Log::info('ImportProcessProductJob: error', [
                'store_id' => $this->storeId,
                'batch_id' => $this->customBatchId,
                'product_id' => $this->productId,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            report($exception);
        }
    }

    private function getStore($storeId): Model|Builder|null
    {
        // Use the primary connection to ensure the store exists on the writer when enforcing FKs
        return Store::query()
            ->where('id', $storeId)
            ->first();
    }
}
