<?php

namespace App\Jobs\Finance;

use App\Mail\Finance\FinancePayoutDetailsExportEmail;
use App\Models\Order\Payout;
use App\Models\Order\PayoutDetail;
use App\Models\Seller\Seller;
use App\Models\Store\Store;
use App\Services\Order\PayoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ExportFinancePayoutDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $storeId;

    protected string $payoutUuid;

    /** @var list<string> */
    protected array $detailUuids;

    protected array $columns = [
        'Order Number',
        'Order Date',
        'Currency',
        'Payment Status',
        'Fulfilment Status',
        'Order Amount',
        'Shipping Label Cost',
        'Taxes Deducted',
        'DogPack Fees',
        'Payment Processing Fees',
        'Refunds',
        'Payout Amount',
        'Seller Discount Contribution',
        'DogPack Discount Contribution',
    ];

    public function __construct(array $data)
    {
        $this->storeId = $data['store_id'];
        $this->payoutUuid = $data['payout_uuid'];
        $this->detailUuids = $data['detail_uuids'];
        $this->onQueue(config('queue.low'));
    }

    public function handle(): void
    {
        Log::info('ExportFinancePayoutDetailsJob started', [
            'store_id' => $this->storeId,
            'payout_uuid' => $this->payoutUuid,
            'detail_count' => count($this->detailUuids),
        ]);

        try {
            $this->runExport();
        } catch (Throwable $e) {
            Log::error('ExportFinancePayoutDetailsJob failed', [
                'store_id' => $this->storeId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('ExportFinancePayoutDetailsJob permanently failed', [
            'store_id' => $this->storeId,
            'message' => $e->getMessage(),
        ]);
    }

    private function runExport(): void
    {
        $store = Store::on('mysql_reader')
            ->with('country')
            ->where('id', $this->storeId)
            ->first();

        if (! $store) {
            Log::warning('ExportFinancePayoutDetailsJob: store not found', ['store_id' => $this->storeId]);

            return;
        }

        $currency = $store->country->currency_code ?? 'CAD';

        $payout = Payout::on('mysql_reader')
            ->where('uuid', $this->payoutUuid)
            ->where('store_id', $store->id)
            ->first();

        if (! $payout) {
            Log::warning('ExportFinancePayoutDetailsJob: payout not found', [
                'store_id' => $this->storeId,
                'payout_uuid' => $this->payoutUuid,
            ]);

            return;
        }

        $detailsByUuid = PayoutDetail::on('mysql_reader')
            ->with(['order'])
            ->where('payout_id', $payout->id)
            ->whereIn('uuid', $this->detailUuids)
            ->get()
            ->keyBy('uuid');

        $calculatedByOrderUuid = collect(PayoutService::calculate($payout))->keyBy('orderUuid');

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $this->columns);

        $orderCount = 0;

        foreach ($this->detailUuids as $detailUuid) {
            $detail = $detailsByUuid->get($detailUuid);
            $order = $detail?->order;
            $row = $order ? $calculatedByOrderUuid->get($order->uuid) : null;

            if (! $order || ! $row) {
                continue;
            }

            fputcsv($handle, $this->buildRow($row, $order, $currency));
            $orderCount++;
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $filename = 'finance-details-export-'.time().'.csv';
        $path = "exports/csv/{$store->slug}/{$filename}";

        Storage::disk('mps3')->put($path, $content);
        Storage::disk('mps3')->setVisibility($path, 'public');

        $url = rtrim(config('services.marketplace_media'), '/').'/'.ltrim($path, '/');

        Log::info('ExportFinancePayoutDetailsJob: CSV uploaded', [
            'store_id' => $this->storeId,
            'path' => $path,
            'order_count' => $orderCount,
            'url' => $url,
        ]);

        $seller = Seller::on('mysql_reader')
            ->select('id', 'email')
            ->where('id', $store->seller_id)
            ->first();

        if (! $seller?->email) {
            Log::warning('ExportFinancePayoutDetailsJob: seller email missing, CSV uploaded but email skipped', [
                'store_id' => $this->storeId,
                'path' => $path,
            ]);

            return;
        }

        Mail::to($seller->email)->send(new FinancePayoutDetailsExportEmail(
            $store->name,
            $filename,
            $orderCount,
            now()->format('Y-m-d H:i:s'),
            $url,
            'selected'
        ));

        Log::info('ExportFinancePayoutDetailsJob completed', [
            'store_id' => $this->storeId,
            'seller_email' => $seller->email,
            'url' => $url,
        ]);
    }

    private function buildRow(array $row, $order, string $currency): array
    {
        return [
            $row['number'],
            $order->created_at,
            $currency,
            $order->payment_status,
            $order->fulfilment_status,
            $row['total'] / 100,
            $row['shipping'] / 100,
            $row['tax'] / 100,
            $row['commission'] / 100,
            $row['stripe'] / 100,
            $row['refunded'] / 100,
            $row['payoutAmount'] / 100,
            $row['total_seller_discount'] / 100,
            $row['total_dogpack_discount'] / 100,
        ];
    }
}
