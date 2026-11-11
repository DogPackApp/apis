<?php

namespace App\Mail\Order;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderExportEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $storeName;

    public $fileName;

    public $exportDate;

    public $filters;

    public $url;

    public $ordersCount;

    public $itemsCount;

    public function __construct($data)
    {
        $this->storeName = $data['store_name'];
        $this->fileName = $data['file_name'];
        $this->exportDate = $data['export_date'];
        $this->filters = $data['filters'];
        $this->url = $data['url'];
        $this->ordersCount = $data['orders_count'];
        $this->itemsCount = $data['items_count'];
    }

    public function build()
    {
        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.order-export-csv')
            ->subject('Order Export CSV File');
    }
}
