<?php

namespace App\Jobs\Order;

use App\Models\Order\StoreOrder;
use App\Models\Stripe\DisputeStripeLog;
use App\Notifications\DisputeReceived;
use App\Notifications\SlackNotifier;
use App\Services\Order\CountryService;
use App\Services\Order\CurrencyService;
use App\Services\Order\PayoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplyDisputeStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const DISPUTE_RECEIVED_FEE_USD_CENTS = 1500;

    private const DISPUTE_FEE_APPLIED_KEY = '_dogpack_dispute_fee_applied';

    private const DISPUTE_COUNTERED_FEE_APPLIED_KEY = '_dogpack_dispute_countered_fee_applied';

    private const DISPUTE_AMOUNT_APPLIED_KEY = '_dogpack_dispute_amount_applied';

    public function __construct(public int $disputeStripeLogId)
    {
        $this->onQueue(config('queue.high'));
    }

    public function handle(): void
    {
        $log = DisputeStripeLog::query()->find($this->disputeStripeLogId);

        if (! $log) {
            return;
        }

        $orders = collect();

        if ($log->payment_intent_id) {
            $orders = StoreOrder::with(['store', 'orderBilling'])
                ->whereHas('paymentDetail', function ($query) use ($log) {
                    $query->where('payment_id', $log->payment_intent_id);
                })
                ->orderBy('id')
                ->get();
        }

        if ($orders->isNotEmpty()) {
            $log->store_order_ids = $orders->pluck('id')->values()->all();
            $log->save();

            if ($log->status) {
                foreach ($orders as $order) {
                    $order->dispute_status = $log->status;
                    $order->save();
                    PayoutService::removeUpcomingDisputeLine($order);
                }
            }

            $this->applyWithdrawnFee($log, $orders);
            $this->applyLostFee($log, $orders);
            $this->applyLostAmount($log, $orders);
            $this->restoreFulfilledPayoutLines($orders);
        }

        if ($log->event_type !== 'charge.dispute.created') {
            return;
        }

        $this->notify($log, $orders);
    }

    /**
     * A fulfilled order whose dispute is finished goes back on the upcoming paycheck.
     * An unfulfilled order stays off until fulfillment adds the line.
     */
    private function restoreFulfilledPayoutLines(Collection $orders): void
    {
        foreach ($orders as $order) {
            if ($order->fulfilment_status !== StoreOrder::ORDER_FULFILLED || $order->disputeBlocksPayout()) {
                continue;
            }

            (new PayoutService((int) $order->store_id))->add($order);
        }
    }

    /**
     * Add Stripe's $15 received fee to stripe_fee, split across the store orders
     * on this payment. A deposited paycheck is left unchanged.
     */
    private function applyWithdrawnFee(DisputeStripeLog $log, Collection $orders): void
    {
        if ($log->event_type !== 'charge.dispute.funds_withdrawn') {
            return;
        }

        $this->applyFee(
            $log,
            $orders,
            'charge.dispute.funds_withdrawn',
            self::DISPUTE_FEE_APPLIED_KEY,
            self::DISPUTE_RECEIVED_FEE_USD_CENTS,
            'USD'
        );
    }

    /**
     * The countered $15 stays with Stripe only when evidence was submitted and the dispute is lost.
     */
    private function applyLostFee(DisputeStripeLog $log, Collection $orders): void
    {
        if ($log->event_type !== 'charge.dispute.closed' || $log->status !== 'lost') {
            return;
        }

        if ($this->evidenceSubmissionCount($log) <= 0) {
            return;
        }

        $this->applyFee(
            $log,
            $orders,
            'charge.dispute.closed',
            self::DISPUTE_COUNTERED_FEE_APPLIED_KEY,
            self::DISPUTE_RECEIVED_FEE_USD_CENTS,
            'USD'
        );
    }

    /**
     * On a loss, add the disputed amount itself on top of stripe_fee.
     */
    private function applyLostAmount(DisputeStripeLog $log, Collection $orders): void
    {
        if ($log->event_type !== 'charge.dispute.closed' || $log->status !== 'lost') {
            return;
        }

        $cents = (int) ($log->amount ?? 0);
        $currency = strtoupper((string) ($log->currency ?? ''));
        if ($cents <= 0 || $currency === '') {
            return;
        }

        $this->applyFee(
            $log,
            $orders,
            'charge.dispute.closed',
            self::DISPUTE_AMOUNT_APPLIED_KEY,
            $cents,
            $currency
        );
    }

    private function applyFee(DisputeStripeLog $log, Collection $orders, string $eventType, string $markerKey, int $totalCents, string $sourceCurrency): void
    {
        if ($orders->isEmpty() || $this->disputeFeeAlreadyApplied($log, $eventType, $markerKey)) {
            return;
        }

        foreach ($orders as $order) {
            if (! $order->orderBilling) {
                throw new \RuntimeException("Order {$order->id} has no billing for dispute fee");
            }
        }

        $shares = $this->centShares($orders, $totalCents);
        $sellerFees = [];

        foreach ($orders as $order) {
            $sellerFees[$order->id] = $this->sellerFeeCents($order, $shares[$order->id] ?? 0, $sourceCurrency);
        }

        DB::transaction(function () use ($log, $orders, $sellerFees, $eventType, $markerKey, $totalCents, $sourceCurrency) {
            $locked = $this->lockDisputeLogs($log, $eventType);

            if ($locked->contains(fn (DisputeStripeLog $row) => $this->feeMarker($row, $markerKey) !== null)) {
                return;
            }

            foreach ($orders as $order) {
                $sellerCents = $sellerFees[$order->id] ?? 0;
                if ($sellerCents <= 0) {
                    continue;
                }

                if (PayoutService::isDeposited($order)) {
                    PayoutService::addDepositedDisputeCollection($order, $sellerCents);

                    continue;
                }

                $billing = $order->orderBilling;
                $billing->stripe_fee = (int) $billing->stripe_fee + $sellerCents;
                $billing->save();
            }

            $current = $locked->firstWhere('id', $log->id) ?? $log;
            $payload = $current->payload ?? [];
            $payload[$markerKey] = [
                'source_cents' => $totalCents,
                'source_currency' => strtoupper($sourceCurrency),
                'seller_fee_cents' => $sellerFees,
            ];
            $current->payload = $payload;
            $current->save();
        });
    }

    private function evidenceSubmissionCount(DisputeStripeLog $log): int
    {
        $count = $log->payload['data']['object']['evidence_details']['submission_count'] ?? 0;

        return (int) $count;
    }

    private function disputeFeeAlreadyApplied(DisputeStripeLog $log, string $eventType, string $markerKey): bool
    {
        $query = DisputeStripeLog::query()->where('event_type', $eventType);

        if ($log->dispute_id) {
            $query->where('dispute_id', $log->dispute_id);
        } else {
            $query->whereKey($log->id);
        }

        return $query->get()->contains(fn (DisputeStripeLog $row) => $this->feeMarker($row, $markerKey) !== null);
    }

    private function lockDisputeLogs(DisputeStripeLog $log, string $eventType): Collection
    {
        $query = DisputeStripeLog::query()->where('event_type', $eventType);

        if ($log->dispute_id) {
            $query->where('dispute_id', $log->dispute_id);
        } else {
            $query->whereKey($log->id);
        }

        return $query->lockForUpdate()->get();
    }

    private function feeMarker(DisputeStripeLog $log, string $markerKey): mixed
    {
        return ($log->payload ?? [])[$markerKey] ?? null;
    }

    /**
     * @return array<int, int> store order id => source cents. The last order takes the remainder.
     */
    private function centShares(Collection $orders, int $totalCents): array
    {
        $orders = $orders->values();
        $weights = $orders->map(function (StoreOrder $order) {
            return max(0, (float) ($order->orderBilling->currency_details['total'] ?? 0));
        });
        $weightTotal = (float) $weights->sum();
        $count = $orders->count();
        $allocated = 0;
        $shares = [];

        foreach ($orders as $index => $order) {
            if ($index === $count - 1) {
                $shares[$order->id] = $totalCents - $allocated;
                break;
            }

            $weight = $weightTotal > 0 ? ($weights[$index] / $weightTotal) : (1 / $count);
            $cents = (int) floor($totalCents * $weight);
            $allocated += $cents;
            $shares[$order->id] = $cents;
        }

        return $shares;
    }

    private function sellerFeeCents(StoreOrder $order, int $sourceCents, string $sourceCurrency): int
    {
        if ($sourceCents <= 0) {
            return 0;
        }

        $sourceCurrency = strtoupper($sourceCurrency);
        $sellerCurrency = $this->sellerCurrency($order);

        if ($sellerCurrency !== null && $sellerCurrency === $sourceCurrency) {
            return $sourceCents;
        }

        $amount = $sourceCents / 100;
        $buyerCurrency = strtoupper($order->getBuyerCurrency());
        $rate = (float) ($order->orderBilling->exchange_rate ?? 0);

        if ($sourceCurrency === $buyerCurrency && $buyerCurrency !== '' && $rate > 0) {
            return (int) round(($amount / $rate) * 100);
        }

        if ($sellerCurrency === null) {
            throw new \RuntimeException("Order {$order->id} has no seller currency for dispute fee");
        }

        $converted = (new CurrencyService)->convert($amount, $sourceCurrency, $sellerCurrency);

        return (int) round($converted['convertedPrice'] * 100);
    }

    private function sellerCurrency(StoreOrder $order): ?string
    {
        $countryId = $order->store?->country_id;
        if ($countryId === null) {
            return null;
        }

        $code = (new CountryService)->getCurrencyCodeByCountryId((int) $countryId);

        return $code !== '' ? strtoupper($code) : null;
    }

    private function notify(DisputeStripeLog $log, $orders): void
    {
        if (empty(config('services.slack.webhook_url'))) {
            return;
        }

        $amount = $log->amount !== null ? number_format($log->amount / 100, 2) : 'N/A';
        $orderLabel = $orders->isEmpty()
            ? 'Order not found'
            : $orders->map(function (StoreOrder $order) {
                $storeName = $order->store?->name ?? 'N/A';

                return $order->order_no.' ('.$storeName.')';
            })->implode(', ');

        try {
            (new SlackNotifier)->notify(new DisputeReceived([
                'dispute_id' => $log->dispute_id,
                'reason' => $log->reason,
                'status' => $log->status,
                'amount' => $amount,
                'currency' => $log->currency,
                'payment_intent_id' => $log->payment_intent_id,
                'orders' => $orderLabel,
            ]));
        } catch (\Throwable $e) {
            Log::error('Failed to send dispute Slack notification: '.$e->getMessage(), [
                'dispute_stripe_log_id' => $log->id,
                'dispute_id' => $log->dispute_id,
            ]);
        }
    }
}
