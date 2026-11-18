<?php

namespace App\Http\Controllers\Order\Refunds;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\Order\OrderResource;
use App\Models\Order\EasyPost;
use App\Models\Order\StoreOrder;
use App\Models\Order\Timeline;
use App\Services\Order\EasyPostService;
use App\Services\Order\Integrations\AvalaraService;
use App\Services\Order\Integrations\Stripe\RefundService;
use App\Services\Order\PayoutService;
use App\Services\Order\RefundMailService;
use App\Services\Order\TimelineService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class RefundOrderPartialController extends Controller
{
    use RespondsWithSellerJson;

    // TODO: REHAN -> A little refactoring is still required
    public function __invoke(Request $request, StoreOrder $order, RefundService $refundService, EasyPostService $easyPostService)
    {
        try {
            DB::beginTransaction();

            // TODO: Refunds is possible until
            // the payout has not been sent to the seller
            // & if the order is CANCELLED

            $payoutSent = PayoutService::isDeposited($order);

            if ($payoutSent) {
                return $this->errorResponse(
                    "The order can't be changed as the payout has already been deposited",
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            if (in_array($order->fulfilment_status, [
                StoreOrder::ORDER_CANCELLED,
            ])) {
                return $this->errorResponse(
                    'Only Unfulfilled Orders can be Refunded Partially',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
            // what items I need to refund
            // how much quantity we need to refund
            $validator = Validator::make($request->all(), [
                'items' => ['required', 'array', 'min:1'],
                'items.*.uuid' => ['required', 'uuid'],
                'items.*.qty' => ['required', 'integer', 'min:1'],
                'reason' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                DB::rollBack();

                return $this->errorResponse(
                    $validator->errors(),
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $order->load(['items', 'refunds', 'orderBilling', 'paymentDetail', 'items.product', 'store:id,country_id']);
            $storeCountryId = $order->store?->country_id;

            $refundable = [];

            $refundLines = [];

            // what was the tax amount on these items
            foreach ($order->items as $item) {
                foreach ($validator->validated()['items'] as $key => $value) {
                    $available = ($item->quantity - $item->refunded);

                    if ($item->uuid === $value['uuid']) {
                        if ($value['qty'] <= $available) {
                            // calculate tax on (price * quality) + ((Tax Amount / quantity) * quantity)

                            // per product value (for buyer)  => round($item->currency_details['converted']['discountedPrice'], 2)
                            // per product value (for seller) => $item->price / 100

                            // per product tax total (for buyer)  => $item->tax['converted'] / 100
                            // per product tax total (for seller) => $item->tax['original'] / 100

                            $convertedTax = isset($item->tax['converted']) ? (($item->tax['converted'] / 100) / $item->quantity) * $value['qty'] : 0;
                            $originalTax = isset($item->tax['original']) ? (($item->tax['original'] / 100) / $item->quantity) * $value['qty'] : 0;

                            $convertedTotal = (round($item->currency_details['converted']['discountedPrice'], 2) * $value['qty']);
                            $originalTotal = ($item->price / 100) * $value['qty'];

                            $convertedAmount = $order->cardRefundCentsForLineMerchandise($convertedTotal);
                            $originalAmount = (int) round(($originalTotal) * 100);

                            $convertedTax = (int) round(($convertedTax) * 100);
                            $originalTax = (int) round(($originalTax) * 100);

                            $item->refunded += $value['qty'];
                            $item->save();

                            $refundable[$item->id] = [
                                'original' => $originalAmount,
                                'converted' => $convertedAmount,
                                'originalTax' => $originalTax,
                                'convertedTax' => $convertedTax,
                                'quantity' => $value['qty'],
                                'refunded_after' => $item->refunded,
                            ];

                            $refundLines[] = [
                                'lineNumber' => (string) $item->product->uuid, // must match original
                                'quantity' => $value['qty'],        // optional if amount is provided
                                'amount' => $convertedTotal,       // pre-tax refund amount
                                'taxIncluded' => false,
                            ];

                        } else {
                            DB::rollBack();

                            return $this->errorResponse(
                                "Only $available can be refunded at the moment",
                                Response::HTTP_UNPROCESSABLE_ENTITY
                            );
                        }
                    }
                }
            }

            // Record the refund rows now. Stripe, the customer email, and wallet
            // adjustments wait until process-refund.
            $refundService->recordPendingItems(
                $order->id,
                collect($refundable),
                $order->paymentDetail?->payment_id,
                $request->reason ?? 'Out of Stock',
                $storeCountryId,
            );

            (new AvalaraService)->refund($order, collect($refundLines)->sum('amount'), "Partial Refund for $order->order_no");
            // mark the orders as partial
            $order->payment_status = StoreOrder::ORDER_PAYMENT_STATUS_PARTIALLY_REFUNDED;
            $order->save();

            (new TimelineService)->for(Timeline::REFUND_PARTIAL_INITIATED, $order->id)->generate();

            $cancelled = false;

            if ($order->items->sum('quantity') === $order->items->sum('refunded')) {

                // Refresh refunds after the item refund above, then refund only this
                // seller's remaining balance (shipping/tax/etc), capped by PI net.
                $order->unsetRelation('refunds');
                $cappedRefund = $refundService->getCappedStoreOrderRefundAmount($order, $storeCountryId);
                $convertedRemainingAmount = (int) ($cappedRefund['amount'] ?? 0);
                $originalRemainingAmount = (int) ($cappedRefund['original'] ?? 0);

                $refundService->recordPendingDelivery(
                    $order->id,
                    $originalRemainingAmount,
                    $convertedRemainingAmount,
                    'Delivery + any pending amounts',
                );

                if ($convertedRemainingAmount > 0) {
                    (new AvalaraService)->delivery($order);
                }

                $order->payment_status = StoreOrder::ORDER_PAYMENT_STATUS_REFUNDED;
                $order->order_status = StoreOrder::ORDER_STATUS_CANCELLED;
                $order->fulfilment_status = StoreOrder::ORDER_CANCELLED;
                $order->save();

                // TODO: Check if there is any active easypost shipping label is available
                $order->load(['easypost']);

                // this means that, this rates were bought and is being used by the seller
                if ($order?->easypost?->total > 0) {
                    $easyPostService->void($order->easyPosts);
                    $order->easypost->refund_status = EasyPost::RE_SUBMITTED;
                }

                $cancelled = true;
                (new PayoutService($order->store_id, true))->add($order);
            }

            (new PayoutService($order->store_id, $cancelled))->reconcileLater($order);
            DB::commit();

            (new RefundMailService)->notifyAdminForReview(
                $order,
                $validator->validated()['items'],
                false,
                false,
                $request->reason ?? 'Out of Stock',
            );

            // send back the order resource
            return OrderResource::make($order);
        } catch (Exception $e) {
            // dd($e);
            DB::rollBack();

            $this->handleException($e);

            return false;
        }
    }
}
