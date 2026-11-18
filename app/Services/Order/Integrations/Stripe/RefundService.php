<?php

namespace App\Services\Order\Integrations\Stripe;

use App\Models\Order\PaymentDetail;
use App\Models\Order\Refund as RefundModel;
use App\Models\Order\RefundDecision;
use App\Models\Order\StoreOrder;
use App\Services\Order\StripeKeyService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Stripe\Charge;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

class RefundService
{
    /**
     * Store item refund rows without charging Stripe. Each row gets a pending decision.
     *
     * @return Collection<int, RefundModel>
     */
    public function recordPendingItems(int $orderId, Collection $items, ?string $paymentId, ?string $reason, ?int $countryId): Collection
    {
        $items = $this->applyPaymentCap($items, $paymentId, $countryId);
        $created = collect();

        foreach ($items as $orderItemId => $item) {
            $refund = RefundModel::create([
                'refund_id' => null,
                'amount' => $item['original'],
                'converted' => $item['converted'],
                'tax' => $item['originalTax'] ?? 0,
                'tax_converted' => $item['convertedTax'] ?? 0,
                'order_item_id' => $orderItemId,
                'store_order_id' => $orderId,
                'reason' => $reason,
            ]);

            $this->createDecision(
                $refund,
                RefundDecision::TYPE_ITEM,
                isset($item['quantity']) ? (int) $item['quantity'] : null,
                isset($item['refunded_after']) ? (int) $item['refunded_after'] : null,
            );

            $created->push($refund);
        }

        return $created;
    }

    public function recordPendingDelivery(int $orderId, int $originalRemainingAmount, int $convertedRemainingAmount, ?string $reason): RefundModel
    {
        $refund = RefundModel::create([
            'refund_id' => null,
            'amount' => $originalRemainingAmount,
            'converted' => $convertedRemainingAmount,
            'store_order_id' => $orderId,
            'reason' => $reason,
        ]);

        $this->createDecision($refund, RefundDecision::TYPE_DELIVERY);

        return $refund;
    }

    public function recordPendingCancellation(int $orderId, int $originalRemainingAmount, int $convertedRemainingAmount, ?string $reason): RefundModel
    {
        $refund = RefundModel::create([
            'refund_id' => null,
            'amount' => $originalRemainingAmount,
            'converted' => $convertedRemainingAmount,
            'store_order_id' => $orderId,
            'reason' => $reason,
        ]);

        $this->createDecision($refund, RefundDecision::TYPE_CANCELLATION);

        return $refund;
    }

    /**
     * Charge Stripe for a stored refund row.
     *
     * Returns the Stripe refund id, or "wallet" when nothing is charged.
     * Returns false when Stripe does not succeed. Buyer cents are capped to the
     * live PaymentIntent net and written back onto the model for the caller to save.
     */
    public function chargeStoredRefund(RefundModel $refund, StoreOrder $order): string|false
    {
        $order->loadMissing(['paymentDetail', 'store']);
        $paymentId = $order->paymentDetail?->payment_id;
        $countryId = $order->store?->country_id;
        $usesConverted = $refund->converted !== null;
        $cents = (int) ($refund->converted ?? $refund->amount ?? 0);

        if (empty($paymentId) || $cents <= 0) {
            return 'wallet';
        }

        $paymentDetail = new PaymentDetail;
        $paymentDetail->payment_id = $paymentId;
        $net = $this->getNetPaymentAmount($paymentDetail, $countryId);

        if (($net['status'] ?? null) !== 'error') {
            $cents = min($cents, max(0, (int) ($net['netAmount'] ?? 0)));
        }

        if ($cents <= 0) {
            if ($usesConverted) {
                $refund->converted = 0;
            } else {
                $refund->amount = 0;
            }

            return 'wallet';
        }

        try {
            Stripe::setApiKey(StripeKeyService::key($countryId));

            $stripeRefund = Refund::create([
                'payment_intent' => $paymentId,
                'amount' => $cents,
                'reason' => null,
            ]);

            if ($stripeRefund?->status !== 'succeeded') {
                return false;
            }

            if ($usesConverted) {
                $refund->converted = $stripeRefund->amount;
            } else {
                $refund->amount = $stripeRefund->amount;
            }

            return $stripeRefund->id;
        } catch (ApiErrorException|Exception $e) {
            Log::error('Stored refund charge failed', [
                'refund_id' => $refund->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Remaining refundable amount for a single store order (seller slice), in cents.
     *
     * Multi-seller checkouts share one PaymentIntent; callers must still cap the Stripe
     * amount with min(converted, PI netAmount) so other sellers are not over-refunded.
     *
     * Uses the same rounding strategy as PaymentIntentService::convertToCents() —
     * (int) round($dollars * 100) — so the cents match what Stripe was originally charged.
     *
     * @return array{converted: int, original: int}
     */
    public function getStoreOrderRefundableAmount(StoreOrder $order): array
    {
        $order->loadMissing(['orderBilling', 'refunds']);

        $convertedTotal = (int) round(((float) ($order->orderBilling->currency_details['total'] ?? 0)) * 100);
        $originalTotal = (int) ($order->orderBilling->total ?? 0);

        // Item/delivery/cancel refunds store buyer cents in `converted`.
        // Older cancel rows stored Stripe cents only in `amount`.
        // Partial item tax (`tax_converted`) is stored but not sent to Stripe today,
        // so do not subtract it here — remaining cancel/delivery should still cover it.
        //
        // Force (int) per-row to avoid float promotion in the sum; then round() before
        // final (int) cast to eliminate any float precision drift from Collection::sum().
        $alreadyConverted = (int) round($order->refunds->sum(
            fn ($refund) => (int) ($refund->converted ?? $refund->amount ?? 0)
        ));

        $alreadyOriginal = (int) round($order->refunds->sum(
            fn ($refund) => ((int) ($refund->amount ?? 0)) + ((int) ($refund->tax ?? 0))
        ));

        return [
            'converted' => max(0, $convertedTotal - $alreadyConverted),
            'original' => max(0, $originalTotal - $alreadyOriginal),
        ];
    }

    /**
     * Stripe refund amount for one store order: seller remaining, capped by PI balance.
     */
    public function getCappedStoreOrderRefundAmount(StoreOrder $order, ?int $countryId = null): array
    {
        $order->loadMissing(['paymentDetail']);

        $refundable = $this->getStoreOrderRefundableAmount($order);
        $stripePaymentModel = $this->getNetPaymentAmount($order->paymentDetail, $countryId);

        $sellerConverted = (int) ($refundable['converted'] ?? 0);
        $piNetAmount = (int) ($stripePaymentModel['netAmount'] ?? 0);

        // If Stripe lookup failed, fall back to this store order's remaining balance.
        if (($stripePaymentModel['status'] ?? null) === 'error') {
            $amount = $sellerConverted;
        } else {
            $amount = min($sellerConverted, max(0, $piNetAmount));
        }

        return [
            'amount' => max(0, $amount),
            'original' => (int) ($refundable['original'] ?? 0),
            'sellerConverted' => $sellerConverted,
            'stripe' => $stripePaymentModel,
        ];
    }

    public function getNetPaymentAmount(PaymentDetail $paymentDetail, ?int $countryId = null): array
    {
        if (empty($paymentDetail->payment_id)) {
            return [
                'totalPaid' => 0,
                'totalRefunded' => 0,
                'netAmount' => 0,
                'currency' => null,
                'status' => 'skipped',
            ];
        }

        try {
            Stripe::setApiKey(StripeKeyService::key($countryId));

            // Get payment intent
            $payment = PaymentIntent::retrieve($paymentDetail->payment_id);

            // Get charges for this payment intent
            $charges = Charge::all([
                'payment_intent' => $paymentDetail->payment_id,
                'limit' => 10,
            ]);

            $totalPaid = $payment->amount; // Amount customer paid
            $totalRefunded = 0;

            // Sum up all refunds
            foreach ($charges->data as $charge) {
                $totalRefunded += $charge->amount_refunded;
            }

            $netAmount = $totalPaid - $totalRefunded;

            return [
                'totalPaid' => $totalPaid,           // What customer originally paid
                'totalRefunded' => $totalRefunded,   // What was refunded
                'netAmount' => $netAmount,           // What you actually received
                'currency' => $payment->currency,
                'status' => $payment->status,
            ];

        } catch (InvalidRequestException|Exception $e) {
            return [
                'totalPaid' => 0,
                'totalRefunded' => 0,
                'netAmount' => 0,
                'currency' => null,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function createDecision(RefundModel $refund, string $type, ?int $quantity = null, ?int $refundedAfter = null): void
    {
        $refund->decision()->create([
            'status' => RefundDecision::STATUS_PENDING,
            'type' => $type,
            'quantity' => $quantity,
            'refunded_after' => $refundedAfter,
        ]);
    }

    /**
     * Cap buyer cents to the PaymentIntent net, and zero them when nothing can be charged.
     */
    private function applyPaymentCap(Collection $items, ?string $paymentId, ?int $countryId): Collection
    {
        $convertedAmountToRefund = (int) $items->pluck('converted')->sum();

        if (! empty($paymentId) && $convertedAmountToRefund > 0) {
            $paymentDetail = new PaymentDetail;
            $paymentDetail->payment_id = $paymentId;
            $net = $this->getNetPaymentAmount($paymentDetail, $countryId);
            if (($net['status'] ?? null) !== 'error') {
                $remaining = max(0, (int) ($net['netAmount'] ?? 0));
                if ($convertedAmountToRefund > $remaining) {
                    $items = $this->capConvertedToRemaining($items, $remaining);
                }
            }
        }

        $convertedAmountToRefund = (int) $items->pluck('converted')->sum();

        if (empty($paymentId) || $convertedAmountToRefund <= 0) {
            $items = $items->map(function (array $item) {
                $item['converted'] = 0;

                return $item;
            });
        }

        return $items;
    }

    /**
     * Reduce line converted cents so they do not exceed remaining Stripe net.
     * Seller `original` amounts are left unchanged for payout math.
     */
    private function capConvertedToRemaining(Collection $items, int $remainingCents): Collection
    {
        $overflow = (int) $items->pluck('converted')->sum() - $remainingCents;
        if ($overflow <= 0) {
            return $items;
        }

        return $items->map(function (array $item) use (&$overflow) {
            if ($overflow <= 0) {
                return $item;
            }

            $converted = (int) $item['converted'];
            $reduce = min($converted, $overflow);
            $item['converted'] = $converted - $reduce;
            $overflow -= $reduce;

            return $item;
        });
    }
}
