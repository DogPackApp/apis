<?php

namespace App\Services\Order;

use App\Models\Order\Payout;
use App\Models\Order\PayoutDetail;
use App\Models\Order\StoreOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PayoutService
{
    public $storeId;

    public $next;

    public $storeService;

    public function __construct($storeId, $cancelled = false)
    {
        $this->storeId = $storeId;
        $this->storeService = new StoreService;

        if ($cancelled) {
            $this->immediateUpcomingPayment();
        } else {
            $this->nextPayout();
        }
    }

    /**
     * Add the new-ly fulfuilled order to the payout based on `$next`
     */
    public function add(StoreOrder $order): Builder|Model|null
    {
        $existing = PayoutDetail::query()
            ->where('store_order_id', $order->id)
            ->where('collection', false)
            ->first();

        if ($existing) {
            return $existing;
        }

        if ($order->disputeBlocksPayout()) {
            return null;
        }

        $order->load(['orderBilling']);

        $payoutDetails = new PayoutDetail;

        $payoutDetails->store_order_id = $order->id;
        $payoutDetails->payout_id = $this->next->id;
        $payoutDetails->commission = $this->commission($order);
        $payoutAmount = $this->value($order) ?? -1;
        // this one added just for totalling it and it is not necessary anymore or find an alternative way
        $payoutDetails->amount += $payoutAmount;

        $this->next->amount += $payoutAmount;
        // this one added just for totalling it and it is not necessary anymore or find an alternative way
        $this->next->payout_amount += $payoutAmount;

        $payoutDetails->save();
        $this->next->save();

        $this->reconcile($this->next);

        return $payoutDetails;
    }

    /**
     * Attach an order to a specific payout. Used when reconcile adds a line
     * after a dispute is finished.
     */
    public function addToPayout(Payout $payout, StoreOrder $order): ?PayoutDetail
    {
        $previous = $this->next;
        $this->next = $payout;

        try {
            $detail = $this->add($order);
        } finally {
            $this->next = $previous;
        }

        return $detail instanceof PayoutDetail ? $detail : null;
    }

    /**
     * Drop an in-progress dispute off an upcoming paycheck.
     * A deposited transfer is left as it was.
     */
    public static function removeUpcomingDisputeLine(StoreOrder $order): bool
    {
        $order->refresh();

        if (! $order->disputeBlocksPayout() || self::isDeposited($order)) {
            return false;
        }

        $existing = PayoutDetail::query()
            ->with(['payout'])
            ->where('store_order_id', $order->id)
            ->where('collection', false)
            ->first();

        if (! $existing || $existing->payout?->status === Payout::DEPOSITED) {
            return false;
        }

        $payout = $existing->payout;
        $existing->delete();

        $payout->amount = collect(self::calculate($payout))->sum('payoutAmount') ?? $payout->amount;
        $payout->save();

        return true;
    }

    /**
     * Calculate the DogPack Commission based on the order value + logic
     */
    private function commission($order): float
    {
        $billing = $order->orderBilling;
        $commissionRate = $billing->commission_rate;
        $totalWithoutTax = ($billing->sellerOrderTotalCents() - $billing->tax) / 100;

        return round($totalWithoutTax * ($commissionRate / 100), 2) ?? 0;
    }

    private function value($order): int
    {
        $refunded = 0;
        $orderBilling = $order->orderBilling;
        $total = $orderBilling->sellerOrderTotalCents();
        $tax = $orderBilling->tax;
        $commissionRate = $orderBilling->commission_rate;

        // if EP exists, only then we should take that into consideration of calculation of payouts
        // otherwise, the shipping amount to be shown in payouts should be 0
        if ($order?->easypost && $order?->easypost?->trk_id && $order->easypost->refund_status === null) {
            $shippingAmount = $order?->easypost->total;
        } else {
            $shippingAmount = 0;
        }

        $shipping = $shippingAmount;
        $stripe = $orderBilling->stripe_fee;

        $commission = intval(($commissionRate / 100) * ($total - $tax));

        $payoutAmount = $total - $tax - $commission - $stripe - $shipping;

        if ($order->payment_status === StoreOrder::ORDER_PAYMENT_STATUS_REFUNDED &&
            $order->fulfilment_status === StoreOrder::ORDER_STATUS_CANCELLED) {
            if ($order?->manualFulfillment) {
                // if it manually fulfilled there is no need to check shipping at all, just remove the stripe fee and send then the rest of the money
                $refunded = $order->orderBilling->total;
                $tax = 0;
            } elseif ($order?->easyposts) {
                $easyPostCancelledAfterShipped = $order->easyposts?->sum('total') ?? 0;
                $refunded = $order->orderBilling->total;
                $shipping += $easyPostCancelledAfterShipped;
                $tax = 0;
            } else {
                // this will execute when the order was cancelled even before it was fulfilled
                $refunded = $order->orderBilling->total;
                $tax = 0;
            }
            // if cancelled the commission is 0
            // TODO: Update this in the DB when cancellation is triggered
            $commission = 0;
            $tax = 0;
            $payoutAmount = ($stripe + $shipping) * -1;
        }

        // if the order is refunded from DP yet, it was fulfilled in Shopify
        // then this section will execute
        if ($order->payment_status === StoreOrder::ORDER_PAYMENT_STATUS_REFUNDED &&
            $order->fulfilment_status === StoreOrder::ORDER_FULFILLED) {

            if ($order?->manualFulfillment) {
                // if it manually fulfilled there is no need to check shipping at all, just remove the stripe fee and send then the rest of the money
                $refunded = $order->orderBilling->total;
            } elseif ($order?->easyposts) {
                $easyPostCancelledAfterShipped = $order->easyposts?->sum('total') ?? 0;
                $refunded = $order->orderBilling->total;
                $shipping += $easyPostCancelledAfterShipped;
            } else {
                // this will execute when the order was cancelled even before it was fulfilled
                $refunded = $order->orderBilling->total;
            }
            // if cancelled the commission is 0
            // TODO: Update this in the DB when cancellation is triggered
            $commission = 0;
            $tax = 0;
            $payoutAmount = ($stripe + $shipping) * -1;
        }

        if ($order->payment_status === StoreOrder::ORDER_PAYMENT_STATUS_PARTIALLY_REFUNDED &&
            $order->fulfilment_status === StoreOrder::ORDER_FULFILLED) {
            if ($order?->manualFulfillment) {
                // if it manually fulfilled there is no need to check shipping at all, just remove the stripe fee and send then the rest of the money
                // TODO: not rounded here
                // TODO: Tax on discount is not calculated when the full refund is initaite from the refund screen
                // TODO: Cancellariton / Manual / Fulfulilled not woring
                $refunded = $order->refunds->sum('amount') + ($order->refunds->sum('tax') ?? 0);
                $tax = $tax - ($order->refunds->sum('tax') ?? 0);
                $commission = intval(($commissionRate / 100) * ($total - $tax - $refunded));
                $payoutAmount = $total - $tax - $commission - $stripe - $shipping;
                $payoutAmount -= $refunded;
            } elseif ($order?->easypost) {
                $refunded = $order->refunds->sum('amount') + ($order->refunds->sum('tax') ?? 0);
                $tax = $tax - ($order->refunds->sum('tax') ?? 0);
                $commission = intval(($commissionRate / 100) * ($total - $tax - $refunded));
                $payoutAmount = $total - $tax - $commission - $stripe - $shipping;
                $payoutAmount -= $refunded;
            }
        }

        return $payoutAmount ?? 0;
    }

    /**
     * This function is to get the next IMMEDIATE Payout regardless of dates or payout date
     * Reason: Cancelled Order needs to get added to the payouts table to the next immediate payout
     * and not +28day rule
     */
    private function immediateUpcomingPayment(): ?object
    {
        $this->next = Payout::query()
            ->where('status', Payout::UPCOMING)
            ->where('store_id', $this->storeId)
            ->orderBy('payout_date')
            ->first();

        if (! $this->next) {
            $processPayoutAt = now()->addDays($storeSettings?->payout_days ?? 28)->toDateString();

            $this->create($processPayoutAt);
        }

        return $this->next;
    }

    /**
     * This function is to get the next UPCOMING Payout
     */
    private function nextPayout(): ?object
    {
        $storeSettings = $this->storeService->getStoreSettings();

        $processPayoutAt = now()->addDays($storeSettings?->payout_days ?? 28)->toDateString();

        $this->next = Payout::query()
            ->where('status', Payout::UPCOMING)
            ->where('store_id', $this->storeId)
            ->whereDate('payout_date', '=', $processPayoutAt)
            ->orderBy('payout_date')
            ->first();

        if (! $this->next) {
            // if not, take the payment greater than `$processPayoutAt` date
            $this->next = Payout::query()
                ->where('status', Payout::UPCOMING)
                ->where('store_id', $this->storeId)
                ->whereDate('payout_date', '>', $processPayoutAt)
                ->orderBy('payout_date', 'asc')
                ->first();
        }

        // if still nothing is available create a new payout line
        if (! $this->next) {
            $this->create($processPayoutAt);
        }

        return $this->next;
    }

    /**
     * This function is to create new Payouts if nothing is existing
     *
     * @param  $processPayoutAt  [this is the date where the current order is eligible for payout]
     *                          explain: we have 3 payouts 14/aug | 28/aug | 11/sept
     *                          take any x order which was fulfilled at 18/aug then it the order eligible is only after 15/sept
     *                          but we don't have a payout line and we cant send 15/sept paypout
     *                          so according to the 14 days pay period: 11/sept + 14 days = sept 25, 2025
     */
    private function create($processPayoutAt): Payout
    {
        // get the last PO available
        $lastPayout = Payout::query()
            ->where('store_id', $this->storeId)
            ->orderBy('payout_date', 'desc')
            ->first();

        $payout = new Payout;

        $payout->stripe_txn_id = null;
        $payout->store_id = $this->storeId;

        if (! $lastPayout) {
            // if last payout is not available which means, the first PO ever for this store
            // then simply create a PO with adding 14 days from now
            $payout->payout_date = now()->addDays(28);
        } else {
            // if last payout IS available which means, then add 14 days
            // from the last PO and create a new PO from it
            $last = $lastPayout->payout_date;
            while ($processPayoutAt >= now()->parse($payout->payout_date)->toDateString()) {
                $payout->payout_date = now()->parse($last)->addDays(StoreOrder::PAYOUT_DAYS);
                $last = $payout->payout_date;
            }
        }

        $payout->status = Payout::UPCOMING;
        $payout->amount = 0;

        $payout->save();

        $this->next = $payout;

        return $payout;
    }

    public function remove($order): bool
    {
        $existing = PayoutDetail::query()
            ->with(['payout'])
            ->where('store_order_id', $order->id)
            ->where('collection', false)
            ->first();

        if (! $existing) {
            return false;
        }

        // remove the payout amount from the payout table
        $existing->payout->amount -= $existing->amount;
        $existing->payout->save();

        $isDeleted = $existing->delete();

        $this->reconcile($existing->payout);

        return $isDeleted;
    }

    /**
     * This is called everything there is a change in the adding/removing of payouts
     * We recalculate the payout value to be accurate
     */
    private function reconcile($payout): bool
    {
        $payout->amount = collect(PayoutService::calculate($payout))->sum('payoutAmount') ?? $payout->amount;
        $payout->save();

        return true;
    }

    public function reconcileLater($order): bool
    {
        $existing = PayoutDetail::query()
            ->with(['payout'])
            ->where('store_order_id', $order->id)
            ->where('collection', false)
            ->first();

        if (! $existing) {
            return false;
        }

        $existing->payout->amount = collect(PayoutService::calculate($existing->payout))->sum('payoutAmount') ?? $existing->payout;
        $existing->payout->save();

        return true;
    }

    public static function isDeposited(StoreOrder $order): bool
    {
        return PayoutDetail::query()
            ->where('store_order_id', $order->id)
            ->where('collection', false)
            ->whereHas('payout', function ($query) {
                $query->where('status', Payout::DEPOSITED);
            })
            ->exists();
    }

    /**
     * Add a dispute charge to one negative line on the store's next upcoming paycheck.
     * The deposited paycheck and stripe_fee stay as they were.
     */
    public static function addDepositedDisputeCollection(StoreOrder $order, int $sellerCents): void
    {
        if ($sellerCents <= 0) {
            return;
        }

        $upcomingDetail = PayoutDetail::query()
            ->where('store_order_id', $order->id)
            ->where('collection', true)
            ->whereHas('payout', function ($query) {
                $query->where('status', Payout::UPCOMING);
            })
            ->first();

        if (! $upcomingDetail) {
            $upcoming = self::soonestUpcomingPayout((int) $order->store_id);
            $upcomingDetail = new PayoutDetail;
            $upcomingDetail->store_order_id = $order->id;
            $upcomingDetail->payout_id = $upcoming->id;
            $upcomingDetail->commission = 0;
            $upcomingDetail->amount = 0;
            $upcomingDetail->collection = true;
            $upcomingDetail->save();
        }

        $upcomingDetail->amount = (int) $upcomingDetail->amount - $sellerCents;
        $upcomingDetail->commission = 0;
        $upcomingDetail->save();

        $payout = $upcomingDetail->payout()->first();
        $payout->amount = collect(self::calculate($payout))->sum('payoutAmount') ?? $payout->amount;
        $payout->save();
    }

    /**
     * The deposited paycheck stays as it was. The seller's later paycheck
     * gets one negative line for what was already sent minus the order's value now.
     */
    public static function syncDepositedDisputeAdjustment(StoreOrder $order): void
    {
        if (! self::isDeposited($order)) {
            return;
        }

        $upcomingDetail = PayoutDetail::query()
            ->where('store_order_id', $order->id)
            ->whereHas('payout', function ($query) {
                $query->where('status', Payout::UPCOMING);
            })
            ->first();

        if (! $upcomingDetail) {
            $upcoming = self::soonestUpcomingPayout((int) $order->store_id);
            $upcomingDetail = new PayoutDetail;
            $upcomingDetail->store_order_id = $order->id;
            $upcomingDetail->payout_id = $upcoming->id;
            $upcomingDetail->commission = 0;
            $upcomingDetail->amount = 0;
            $upcomingDetail->save();
        }

        $payout = $upcomingDetail->payout()->first();
        $calculated = collect(self::calculate($payout));
        $row = $calculated->firstWhere('orderUuid', $order->uuid);

        $upcomingDetail->amount = (int) ($row['payoutAmount'] ?? 0);
        $upcomingDetail->commission = 0;
        $upcomingDetail->save();

        $payout->amount = $calculated->sum('payoutAmount') ?? $payout->amount;
        $payout->save();
    }

    private static function soonestUpcomingPayout(int $storeId): Payout
    {
        $existing = Payout::query()
            ->where('status', Payout::UPCOMING)
            ->where('store_id', $storeId)
            ->orderBy('payout_date')
            ->first();

        if ($existing) {
            return $existing;
        }

        return (new self($storeId))->next;
    }

    /**
     * Function to Calculate the Amount for the given Payout
     */
    public static function calculate(Payout $payout): array
    {
        $payout->load(['orders.orderBilling', 'orders.easypost', 'orders.easyposts', 'orders.manualFulfillment', 'orders.manualFulfillment']);

        // The finance page sets details to a paginator. Query the lines directly
        // so collection rows are not read off that paginator.
        $payoutDetails = PayoutDetail::query()
            ->where('payout_id', $payout->id)
            ->with('order')
            ->get();
        $normalOrderIds = $payoutDetails
            ->where('collection', false)
            ->pluck('store_order_id');
        $collectionDetails = $payoutDetails->where('collection', true);

        $depositedAmounts = $payout->status === Payout::UPCOMING && $normalOrderIds->isNotEmpty()
            ? PayoutDetail::query()
                ->selectRaw('store_order_id, SUM(amount) as deposited_amount')
                ->whereIn('store_order_id', $normalOrderIds)
                ->where('collection', false)
                ->whereHas('payout', function ($query) {
                    $query->where('status', Payout::DEPOSITED);
                })
                ->groupBy('store_order_id')
                ->pluck('deposited_amount', 'store_order_id')
            : collect();

        $all = [];
        $seenOrderIds = [];

        foreach ($payout->orders as $order) {
            if (! $normalOrderIds->contains($order->id) || isset($seenOrderIds[$order->id])) {
                continue;
            }

            $seenOrderIds[$order->id] = true;

            $refunded = 0;
            $orderBilling = $order->orderBilling;
            $total = $orderBilling->sellerOrderTotalCents();

            $tax = $orderBilling->tax;

            // if EP exists, only then we should take that into consideration of calculation of payouts
            // otherwise, the shipping amount to be shown in payouts should be 0
            if ($order?->easypost && $order?->easypost?->trk_id && $order->easypost->refund_status === null) {
                $shippingAmount = $order?->easypost->total;
            } else {
                $shippingAmount = 0;
            }

            $shipping = $shippingAmount;
            $stripe = $orderBilling->stripe_fee;
            $commissionRate = $orderBilling->commission_rate;

            $commission = intval(($commissionRate / 100) * ($total - $tax));

            $payoutAmount = $total - $tax - $commission - $stripe - $shipping;

            // refunded and canceled orders
            if ($order->payment_status === StoreOrder::ORDER_PAYMENT_STATUS_REFUNDED &&
                $order->fulfilment_status === StoreOrder::ORDER_STATUS_CANCELLED) {
                if ($order?->manualFulfillment) {
                    // if it manually fulfilled there is no need to check shipping at all, just remove the stripe fee and send then the rest of the money
                    $refunded = $order->orderBilling->total;
                } elseif ($order?->easyposts) {
                    $easyPostCancelledAfterShipped = $order->easyposts?->sum('total') ?? 0;
                    $refunded = $order->orderBilling->total;
                    $shipping += $easyPostCancelledAfterShipped;
                } else {
                    // this will execute when the order was cancelled even before it was fulfilled
                    $refunded = $order->orderBilling->total;
                }
                // if cancelled the commission is 0
                // TODO: Update this in the DB when cancellation is triggered
                $commission = 0;
                $tax = 0;
                $payoutAmount = ($stripe + $shipping) * -1;
            }

            // if the order is refunded from DP yet, it was fulfilled in Shopify
            // then this section will execute
            if ($order->payment_status === StoreOrder::ORDER_PAYMENT_STATUS_REFUNDED &&
                $order->fulfilment_status === StoreOrder::ORDER_FULFILLED) {

                if ($order?->manualFulfillment) {
                    // if it manually fulfilled there is no need to check shipping at all, just remove the stripe fee and send then the rest of the money
                    $refunded = $order->orderBilling->total;
                } elseif ($order?->easyposts) {
                    $easyPostCancelledAfterShipped = $order->easyposts?->sum('total') ?? 0;
                    $refunded = $order->orderBilling->total;
                    $shipping += $easyPostCancelledAfterShipped;
                } else {
                    // this will execute when the order was cancelled even before it was fulfilled
                    $refunded = $order->orderBilling->total;
                }
                // if cancelled the commission is 0
                // TODO: Update this in the DB when cancellation is triggered
                $commission = 0;
                $tax = 0;
                $payoutAmount = ($stripe + $shipping) * -1;
            }

            // partial refunded + fulfilled orders
            if ($order->payment_status === StoreOrder::ORDER_PAYMENT_STATUS_PARTIALLY_REFUNDED &&
                $order->fulfilment_status === StoreOrder::ORDER_FULFILLED) {
                if ($order?->manualFulfillment) {
                    // if it manually fulfilled there is no need to check shipping at all, just remove the stripe fee and send then the rest of the money
                    // TODO: not rounded here
                    // TODO: Tax on discount is not calculated when the full refund is initaite from the refund screen
                    // TODO: Cancellariton / Manual / Fulfulilled not woring
                    $refunded = $order->refunds->sum('amount') + ($order->refunds->sum('tax') ?? 0);
                    $tax = $tax - ($order->refunds->sum('tax') ?? 0);
                    $commission = intval(($commissionRate / 100) * ($total - $tax - $refunded));
                    $payoutAmount = $total - $tax - $commission - $stripe - $shipping;
                    $payoutAmount -= $refunded;
                } elseif ($order?->easypost) {
                    $refunded = $order->refunds->sum('amount') + ($order->refunds->sum('tax') ?? 0);
                    $tax = $tax - ($order->refunds->sum('tax') ?? 0);
                    $commission = intval(($commissionRate / 100) * ($total - $tax - $refunded));
                    $payoutAmount = $total - $tax - $commission - $stripe - $shipping;
                    $payoutAmount -= $refunded;
                }
            }

            if ($depositedAmounts->has($order->id)) {
                $payoutAmount -= (int) $depositedAmounts[$order->id];
            }

            $displayDpContribution = $orderBilling->displayDpContributionCents();

            $all[] = [
                'number' => $order->order_no,
                'orderUuid' => $order->uuid,
                'detailUuid' => null,
                'collection' => false,
                'total' => $total,
                'tax' => $tax,
                'commission' => $commission,
                'dp_contribution' => (int) ($orderBilling->dp_contribution ?? 0),
                'display_dp_contribution' => $displayDpContribution,
                'stripe' => $stripe,
                'shipping' => $shipping,
                'refunded' => $refunded,
                'payoutAmount' => $payoutAmount,
                'total_seller_discount' => $orderBilling->seller_contribution ?? 0,
                'total_dogpack_discount' => $displayDpContribution,
            ];
        }

        foreach ($collectionDetails as $detail) {
            $collectionOrder = $detail->order;
            if (! $collectionOrder) {
                continue;
            }

            $all[] = [
                'number' => $collectionOrder->order_no,
                'orderUuid' => $collectionOrder->uuid,
                'detailUuid' => $detail->uuid,
                'collection' => true,
                'total' => 0,
                'tax' => 0,
                'commission' => 0,
                'dp_contribution' => 0,
                'display_dp_contribution' => 0,
                'stripe' => 0,
                'shipping' => 0,
                'refunded' => 0,
                'payoutAmount' => (int) $detail->amount,
                'total_seller_discount' => 0,
                'total_dogpack_discount' => 0,
            ];
        }

        return $all;
    }
}
