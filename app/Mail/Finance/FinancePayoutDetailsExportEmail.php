<?php

namespace App\Mail\Finance;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FinancePayoutDetailsExportEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $storeName,
        public string $fileName,
        public int $orderCount,
        public string $exportDate,
        public string $url,
        public string $statusFilter,
    ) {
        $this->onQueue(config('queue.low'));
    }

    public function build()
    {
        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.finance-payout-details-export-csv')
            ->subject('Finance Details Export CSV File');
    }
}
