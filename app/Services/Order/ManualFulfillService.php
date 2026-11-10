<?php

namespace App\Services\Order;

use App\Events\OrderFulFilled;
use App\Http\Resources\Order\ManualFulfillmentResource;
use App\Mail\Order\OrderAddedTrackingMail;
use App\Mail\Order\OrderAskReviewMail;
use App\Mail\Order\OrderManualFulfilledMail;
use App\Models\Order\Concerns\UpdateOrderStatus;
use App\Models\Order\ManualFulfillment;
use App\Models\Order\StoreOrder;
use App\Models\Order\Timeline;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class ManualFulfillService
{
    use UpdateOrderStatus;

    public function manualFulfill(StoreOrder $order, $params, $fulfilledFrom = 'dogpack'): ManualFulfillmentResource|JsonResponse
    {
        if ($order->fulfilment_status === StoreOrder::ORDER_FULFILLED || $order->fulfilment_status === StoreOrder::ORDER_PARTIALLY_FULFILLED) {
            $order->load(['manualFulfillment']);
            $manual = $order->manualFulfillment;
            // case: when previously, we fulfilled a DIGITAL Product and the status are set as above conditions
            // then we do not have any ManualFulfillment Model available, and hence we need to create it again.
            if (! $manual) {
                $manual = new ManualFulfillment;
            }
        } else {
            $manual = new ManualFulfillment;
        }

        $manual->tracking = $params['tracking'] ?? null;
        $manual->tracking_url = $params['tracking_url'] ?? null;
        $manual->store_order_id = $order->id;
        $manual->carrier = $params['carrier'] ?? null;
        $manual->save();

        $previousStatus = $order->fulfilment_status;

        $this->markAsFulfilled($order);
        $this->markOrderStatus($order, StoreOrder::ORDER_STATUS_MANUAL)->save();

        $order->save();

        // Add payout
        (new PayoutService($order->store_id))->add($order);

        $customerEmailAddress = $order->customer->email ?? config('mail.address.from');

        // Handle tracking email
        if ($manual->tracking) {
            (new TimelineService)->for(Timeline::FULFILLMENT_MANUAL_TRACKING, $order->id)->generate();
            Mail::to($customerEmailAddress)
                ->send(new OrderAddedTrackingMail($order->customer, $order, $manual->tracking, $manual->carrier, $manual->tracking_url));
        }

        // Handle first-time fulfillment
        if ($previousStatus !== StoreOrder::ORDER_FULFILLED || $previousStatus !== StoreOrder::ORDER_PARTIALLY_FULFILLED) {

            // send details of fulfillment to shopify
            if ($fulfilledFrom == 'dogpack' && $order->source_id != null) {
                event(new OrderFulFilled($order));
            }

            (new TimelineService)->for(Timeline::FULFILLMENT_MANUAL_COMPLETED, $order->id)->generate();

            $order->load(['customer']);

            Mail::to($customerEmailAddress)
                ->send(new OrderManualFulfilledMail($order->customer, $order));

            // Schedule review email after 14 days
            // $delay = Carbon::now()->addMinutes(2);
            $delay = Carbon::now()->addDays(14);
            Mail::to($customerEmailAddress)
                ->later($delay, new OrderAskReviewMail($order->customer, $order->uuid));
        }

        return new ManualFulfillmentResource($manual);
    }

    public function manualFulfillDigital(StoreOrder $order, $params, $fulfilledFrom = 'dogpack'): bool
    {
        $previousStatus = $order->fulfilment_status;

        $this->markAsFulfilled($order);
        // Mark only if pending since this is a digital fulfillment it should not overwrite the status which was previously set by other items if any.
        if ($order->order_status === StoreOrder::ORDER_STATUS_PENDING) {
            $this->markOrderStatus($order, StoreOrder::ORDER_STATUS_MANUAL)->save();
        }
        $order->save();

        // Add payout
        (new PayoutService($order->store_id))->add($order);

        $customerEmailAddress = $order->customer->email ?? config('mail.address.from');

        // Handle first-time fulfillment
        if ($previousStatus !== StoreOrder::ORDER_FULFILLED || $previousStatus !== StoreOrder::ORDER_PARTIALLY_FULFILLED) {

            // send details of fulfillment to shopify
            if ($fulfilledFrom == 'dogpack' && $order->source_id != null) {
                event(new OrderFulFilled($order));
            }

            (new TimelineService)->for(Timeline::FULFILLMENT_MANUAL_COMPLETED, $order->id)->generate();

            $order->load(['customer']);

            Mail::to($customerEmailAddress)
                ->send(new OrderManualFulfilledMail($order->customer, $order));

            // Schedule review email after 14 days
            // $delay = Carbon::now()->addMinutes(2);
            $delay = Carbon::now()->addDays(14); // Adding 14 days delay
            Mail::to($customerEmailAddress)
                ->later($delay, new OrderAskReviewMail($order->customer, $order->uuid));
        }

        return true;
    }
}
