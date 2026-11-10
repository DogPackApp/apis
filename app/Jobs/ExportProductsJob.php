<?php

namespace App\Jobs;

use App\Mail\ProductsExportEmail;
use App\Models\Product\ExportProduct;
use App\Models\Product\Item;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ExportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batchID;

    protected $status;

    public $store;

    public function __construct(array $data)
    {
        $this->batchID = $data['batch_id'];
        $this->status = $data['status'] ?? null;
        $this->store = $data['store_id']
            ? Store::query()->where('id', $data['store_id'])->first()
            : null;
    }

    public function handle(): void
    {
        $exportProducts = ExportProduct::where('batch_id', $this->batchID)
            ->pluck('product_uuid')
            ->filter()
            ->values()
            ->toArray();

        $exportProducts = ! empty($exportProducts) ? $exportProducts : null;

        Item::query()
            ->with(['media', 'category', 'sections', 'policy'])
            ->where('store_id', $this->store->id)
            ->when($this->status ?? null, function ($query, $status) {
                $query->where('status', Item::STATUS[$status]);
            })->when($exportProducts ?? null, function ($query, $products) {
                $query->whereIn('uuid', $products);
            })->get();

        $seller = Seller::query()->select('id', 'email')->where('id', $this->store->seller_id)->first();

        Mail::to($seller->email)->send(new ProductsExportEmail($this->store->name));
    }
}
