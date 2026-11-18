<?php

namespace App\Mail\Product;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProductsImportEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $storeName;

    public string $importStartedAt;

    public string $importCompletedAt;

    public int $totalProductsImported;

    public int $totalProductsFailed;

    /**
     * @param  array{store_name: string, started_at: mixed, completed_at: mixed, total_products_imported: int, total_products_failed: int}  $data
     */
    public function __construct(array $data)
    {
        $this->storeName = $data['store_name'];
        $this->importStartedAt = (string) $data['started_at'];
        $this->importCompletedAt = (string) $data['completed_at'];
        $this->totalProductsImported = $data['total_products_imported'];
        $this->totalProductsFailed = $data['total_products_failed'];
    }

    public function build(): self
    {
        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('emails.product.import-product-complete')
            ->subject('Product Import Complete');
    }
}
