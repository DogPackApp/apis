<?php

namespace App\Http\Controllers\Order\Cancel;

use App\Exceptions\EasyPostPreTransitException;
use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\Order\OrderResource;
use App\Models\Order\StoreOrder;
use App\Models\Order\Timeline;
use App\Services\Order\EasyPostService;
use App\Services\Order\Integrations\AvalaraService;
use App\Services\Order\Integrations\Stripe\RefundService;
use App\Services\Order\PayoutService;
use App\Services\Order\RefundMailService;
use App\Services\Order\TimelineService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CancelOrderController extends Controller
{
    use RespondsWithSellerJson;

    public EasyPostService $easyPostService;

    public bool $wasPartiallyRefunded = false;

    public function __construct()
    {
        $this->easyPostService = new EasyPostService;
    }

    public function __invoke(Request $request, StoreOrder $order, RefundService $refundService): OrderResource|JsonResponse
    {
        try {
            DB::beginTransaction();
            // check if the order is not fulfilled
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
                    'Order already cancelled',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $this->wasPartiallyRefunded = (bool) ($order->payment_status === StoreOrder::ORDER_PAYMENT_STATUS_PARTIALLY_REFUNDED);

            // void the label
            if ($order?->easypost?->total > 0) {
                $voidedEasyPosts = $this->easyPostService->void($order->easypost);
            }

            // get the total value of this store order (not the shared multi-seller PaymentIntent)
            $order->load(['orderBilling', 'paymentDetail', 'refunds', 'store:id,country_id']);
            $storeCountryId = $order->store?->country_id;

            $cappedRefund = $refundService->getCappedStoreOrderRefundAmount($order, $storeCountryId);
            $convertedRemainingAmount = (int) ($cappedRefund['amount'] ?? 0);
            $originalRemainingAmount = (int) ($cappedRefund['original'] ?? 0);

            // Stripe, the customer email, and wallet adjustments wait until process-refund.
            // Always record a row, including a zero converted amount, so that step has a decision to run.
            $refundService->recordPendingCancellation(
                $order->id,
                $originalRemainingAmount,
                $convertedRemainingAmount,
                $request->reason ?? 'Out of Stock',
            );

            $order->fulfilment_status = StoreOrder::ORDER_CANCELLED;
            $order->payment_status = StoreOrder::ORDER_PAYMENT_STATUS_REFUNDED;
            $order->order_status = StoreOrder::ORDER_STATUS_CANCELLED;
            $order->save();

            // TODO: Currently using same endpoints for full refund + cancellation
            // I do no have any differentiator and hence will implement REFUND_FULL_INITIATED, later
            // (new TimelineService())->for(Timeline::REFUND_FULL_INITIATED, $order->id)->generate();
            (new TimelineService)->for(Timeline::ORDER_CANCELLED, $order->id)->generate();

            (new PayoutService($order->store_id, true))->add($order);
            (new PayoutService($order->store_id, true))->reconcileLater($order);

            if ($this->wasPartiallyRefunded) {

                $order->loadMissing(['refunds', 'orderBilling']);

                $subtotal = ($order->orderBilling->currency_details['subtotal'] ?? 0) * 100;
                $totalRefunded = $order->refunds->whereNotNull('order_item_id')->sum('converted'); // 200
                $remainingRefund = $subtotal - $totalRefunded;

                if ($remainingRefund > 0) {
                    (new AvalaraService)
                        ->refund($order, $remainingRefund / 100, "Cancelled - Refunding Remaining for $order->order_no");
                    (new AvalaraService)->delivery($order);
                }

            } else {
                (new AvalaraService)->cancel($order);
            }

            DB::commit();

            (new RefundMailService)->notifyAdminForReview(
                $order,
                [],
                $this->wasPartiallyRefunded,
                true,
                $request->reason ?? 'Out of Stock',
            );

            return OrderResource::make($order);
        } catch (EasyPostPreTransitException $e) {

            return $this->errorResponse($e->getMessage());
        } catch (Exception $e) {

            DB::rollBack();

            return $this->handleException($e);
        }
    }
}
