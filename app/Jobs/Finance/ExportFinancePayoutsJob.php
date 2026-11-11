<?php

namespace App\Jobs\Finance;

use App\Mail\Finance\FinancePayoutExportEmail;
use App\Models\Order\Payout;
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

class ExportFinancePayoutsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $storeId;

    /** @var list<string> */
    protected array $payoutUuids;

    protected array $columns = [
        'Payout Date',
        'Status',
        'Currency',
        'Total Order Amounts',
        'Shipping Labels Purchased',
        'Taxes Deducted',
        'DogPack Fees',
        'Payment Processing Fees',
        'Refunds',
        'Total Payout Amount',
        'Total Seller Discount',
        'Total DogPack Discount',
    ];

    public function __construct(array $data)
    {
        $this->storeId = $data['store_id'];
        $this->payoutUuids = $data['payout_uuids'];
        $this->onQueue(config('queue.low'));
    }

    public function handle(): void
    {
        Log::info('ExportFinancePayoutsJob started', [
            'store_id' => $this->storeId,
            'payout_count' => count($this->payoutUuids),
        ]);

        try {
            $this->runExport();
        } catch (Throwable $e) {
            Log::error('ExportFinancePayoutsJob failed', [
                'store_id' => $this->storeId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('ExportFinancePayoutsJob permanently failed', [
            'store_id' => $this->storeId,
            'message' => $e->getMessage(),
        ]);
    }

    private function runExport(): void
    {
        $store = Store::query()
            ->with('country')
            ->where('id', $this->storeId)
            ->first();

        if (! $store) {
            Log::warning('ExportFinancePayoutsJob: store not found', ['store_id' => $this->storeId]);

            return;
        }

        $payoutsByUuid = Payout::query()
            ->with(['store.country'])
            ->where('store_id', $store->id)
            ->whereIn('uuid', $this->payoutUuids)
            ->get()
            ->keyBy('uuid');

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $this->columns);

        $exportedCount = 0;

        foreach ($this->payoutUuids as $uuid) {
            $payout = $payoutsByUuid->get($uuid);

            if (! $payout) {
                continue;
            }

            fputcsv($handle, $this->buildRow($payout));
            $exportedCount++;
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $filename = 'finance-export-'.time().'.csv';
        $path = "exports/csv/{$store->slug}/{$filename}";

        Storage::disk('mps3')->put($path, $content);
        Storage::disk('mps3')->setVisibility($path, 'public');

        $url = rtrim(config('services.marketplace_media'), '/').'/'.ltrim($path, '/');

        Log::info('ExportFinancePayoutsJob: CSV uploaded', [
            'store_id' => $this->storeId,
            'path' => $path,
            'payout_count' => $exportedCount,
            'url' => $url,
        ]);

        $seller = Seller::query()
            ->select('id', 'email')
            ->where('id', $store->seller_id)
            ->first();

        if (! $seller?->email) {
            Log::warning('ExportFinancePayoutsJob: seller email missing, CSV uploaded but email skipped', [
                'store_id' => $this->storeId,
                'path' => $path,
            ]);

            return;
        }

        Mail::to($seller->email)->send(new FinancePayoutExportEmail(
            $store->name,
            $filename,
            $exportedCount,
            now()->format('Y-m-d H:i:s'),
            $url,
            'selected'
        ));

        Log::info('ExportFinancePayoutsJob completed', [
            'store_id' => $this->storeId,
            'seller_email' => $seller->email,
        ]);
    }

    private function buildRow(Payout $payout): array
    {
        $calculated = collect(PayoutService::calculate($payout));
        $currency = $payout->store->country->currency_code ?? 'CAD';

        return [
            $payout->payout_date,
            $payout->status,
            $currency,
            $calculated->sum('total') / 100,
            $calculated->sum('shipping') / 100,
            $calculated->sum('tax') / 100,
            $calculated->sum('commission') / 100,
            $calculated->sum('stripe') / 100,
            $calculated->sum('refunded') / 100,
            $calculated->sum('payoutAmount') / 100,
            $calculated->sum('total_seller_discount') / 100,
            $calculated->sum('total_dogpack_discount') / 100,
        ];
    }
}
