<?php

namespace App\Jobs\Product;

use App\Mail\Product\ProductsImportEmail;
use App\Models\Product\ImportProduct;
use App\Models\Store\Store;
use Exception;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ImportProcessCSVJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $batchId;

    protected null|Builder|Model|Store $store;

    protected int|string $storeId;

    protected $startTime;

    public function __construct(string $batchId, string $storeId, $startTime)
    {
        $this->batchId = $batchId;
        $this->storeId = $storeId;
        $this->startTime = $startTime;
        $this->store = $this->getStore($storeId);
    }

    public function handle(): void
    {
        try {
            if (! $this->store) {
                return;
            }
            $products = ImportProduct::where('batch_id', $this->batchId)
                ->whereNull('errors')
                ->where('is_validated', 1)
                ->where('is_imported', '!=', 1)
                ->pluck('id');

            $jobs = [];
            foreach ($products as $productId) {
                $jobs[] = new ImportProcessProductJob($this->batchId, $productId, $this->store->id);
            }

            $metaData = [
                'email' => $this->store->seller?->email ?? config('services.DOGPACK_EMAIL'),
                'store_name' => $this->store->name,
                'started_at' => $this->startTime,
                'timezone' => $this->store->timezone,
            ];

            Bus::batch($jobs)
                ->onQueue('mkt-high')
                ->catch(function (Batch $batch, Throwable $e) {
                    // Report first encountered error
                    report($e);
                })->finally(function (Batch $batch) use ($metaData) {
                    // The batch has finished executing (regardless of failure)...
                    if ($batch->finished()) {
                        Mail::to($metaData['email'])->send(new ProductsImportEmail(
                            [...$metaData,
                                'completed_at' => now(),
                                'total_products_imported' => $batch->totalJobs - $batch->failedJobs,
                                'total_products_failed' => $batch->failedJobs,
                            ]
                        ));
                    } else {
                        Log::error('No Email was sent since the batch is not finished', ['batch_id' => $batch->id, 'failed_jobs' => $batch->failedJobs]);
                    }
                })
                ->dispatch();

        } catch (Exception $exception) {
            report($exception);
        }
    }

    private function getStore($storeId): Model|Builder|null
    {
        return Store::query()
            ->with('seller:id,email')
            ->where('id', $storeId)
            ->first();
    }
}
