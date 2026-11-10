<?php

namespace App\Mail\Product;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProductsExportEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $storeName,
        public string $fileName,
        public int $numberOfProducts,
        public string $exportDate,
        public string $url,
    ) {
        $this->onQueue(config('queue.low'));
    }

    public function build(): self
    {
        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('emails.product.product-export-csv', [
                'store_name' => $this->storeName,
                'file_name' => $this->fileName,
                'number_of_products' => $this->numberOfProducts,
                'export_date' => $this->exportDate,
                'url' => $this->url,
            ])
            ->subject('Product Export CSV File');
    }
}
