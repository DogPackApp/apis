<?php

namespace App\Http\Controllers\Order\Timeline;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Mail\Order\BaseMailer;
use App\Models\Order\StoreOrder;
use App\Models\Order\Timeline;
use App\Services\Order\AmountService;
use App\Services\Order\CountryService;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TimelineEmailRenderController extends Controller
{
    use BaseMailer;
    use RespondsWithSellerJson;

    public $currency;

    const ORDER_EMAIL = 'order.email';

    const ORDER_EMAIL_REVIEW = 'order.email.review';

    const REFUND_EMAIL = 'refund.email';

    const FULFILLMENT_MANUAL_TRACKING_EMAIL = 'fulfillment.manual.tracking.email';

    const FULFILLMENT_MANUAL_TRACKING = 'fulfillment.manual.tracking';

    const FULFILLMENT_MANUAL_COMPLETED = 'fulfillment.manual.completed';

    const FULFILLMENT_EMAIL = 'fulfillment.email';

    const FULFILLMENT_COMPLETED = 'fulfillment.completed';

    const FULFILLMENT_VOIDED = 'fulfillment.voided';

    public $countryService;

    public $order;

    public $timeline;

    public function __construct()
    {
        $this->countryService = app(CountryService::class);
    }

    public function __invoke(StoreOrder $order, string $event, ?Timeline $timeline = null): array|JsonResponse
    {
        try {
            $this->order = $order;
            $this->timeline = $timeline;

            $email = match ($event) {
                self::ORDER_EMAIL => $this->buildOrderEmailBuyer(),
                self::ORDER_EMAIL_REVIEW => $this->buildOrderEmailReview(),
                self::REFUND_EMAIL => $this->buildOrderRefundEmail(),
                self::FULFILLMENT_MANUAL_TRACKING_EMAIL => $this->buildOrderTracking(),
                self::FULFILLMENT_MANUAL_TRACKING => $this->buildOrderTracking(),
                self::FULFILLMENT_EMAIL => $this->buildOrderFulfillment(),
                self::FULFILLMENT_MANUAL_COMPLETED => $this->buildManualFulfillmentCompleted(),
                self::FULFILLMENT_COMPLETED => $this->buildFulfillmentCompleted(),
                self::FULFILLMENT_VOIDED => $this->buildFulfillmentVoided(),
            };

            return [
                'html' => $email,
            ];

        } catch (Exception $e) {
            dd($e);

            return $this->handleException($e);
        }
    }

    protected function buildOrderEmailBuyer(): string
    {
        // TODO: REHAN: Need to improvise this buddy
        $this->order->load(['customer', 'store.seller', 'orderBilling']);

        $orders = StoreOrder::query()
            ->with(['store', 'customer', 'items', 'orderBilling', 'paymentDetail'])
            ->where('uuid', $this->order->uuid)
            ->get();

        $summary = [
            'subtotal' => 0,
            'delivery' => 0,
            'tax' => 0,
            'discount' => 0,
            'wallet_discount' => 0,
            'total' => 0,
        ];

        $delivery = [];
        $deliveryPerOrder = [];

        foreach ($orders as $order) {
            $totals = (new AmountService($order))->orderTotal();

            $this->identifyCurrency($order->customer);

            $summary['subtotal'] += $totals['subtotal'];
            $summary['delivery'] += $totals['delivery'];
            $summary['tax'] += $totals['tax'];
            $summary['discount'] += $totals['discount'];
            $wallet = $order->orderBilling?->wallet;
            $summary['wallet_discount'] += is_array($wallet)
                ? round((float) ($wallet['discount_amount'] ?? $totals['wallet_discount'] ?? 0), 2)
                : round((float) ($totals['wallet_discount'] ?? 0), 2);
            $summary['total'] += $totals['total'];

            // Store delivery info per order
            $deliveryPerOrder[$order->id] = $this->delivery($order, $summary['delivery']);

            // Keep last one for backward compatibility
            $delivery = $deliveryPerOrder[$order->id];
        }
        $isUk = $this->countryService->matchCountryCode($this->order->customer->shipping_address['country'] ?? null, CountryService::COUNTRY_CODES['UK']);

        return view('marketplace.emails.order-confirmation-buyer', [
            'orders' => $orders,
            'summary' => $summary,
            'user' => [
                'first_name' => $this->order->customer->name,
            ],
            'delivery' => $delivery,
            'deliveryPerOrder' => $deliveryPerOrder,
            'currency' => $this->currency,
            'isUk' => $isUk,
            'isRenewal' => $orders->contains(fn ($order) => $order->subscription_id !== null),
        ]);
    }

    protected function buildOrderEmailReview(): string
    {
        $this->order->load(['customer']);

        $isUk = $this->countryService->matchCountryCode($this->order->customer->shipping_address['country'] ?? null, CountryService::COUNTRY_CODES['UK']);

        return view('marketplace.emails.order-confirmation-seller', [
            'order_no' => $this->order->order_no,
            'order_uuid' => $this->order->uuid,
            'name' => $this->order->customer->name,
            'order' => $this->order,
            'isUk' => $isUk,
        ])->render();
    }

    protected function buildOrderRefundEmail(): string
    {
        if ($this->timeline && $this->timeline->email) {
            return $this->timeline->email;
        } else {
            return $this->errorResponse(
                'Email content not found',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    protected function buildOrderTracking(): string
    {
        $this->order->load(['customer', 'manualFulfillment']);

        return view('marketplace.emails.tracking-added', [
            'order' => $this->order,
            'user' => $this->order->customer,
            'tracking' => $this->order->manualFulfillment->tracking,
            'trackingUrl' => $this->order->manualFulfillment->tracking_url,
            'carrier' => $this->order->manualFulfillment->carrier,
        ])->render();
    }

    protected function buildOrderFulfillment(): string
    {
        $this->order->load(['customer']);

        $delivery = [];

        if ($this->order->shipping_method === StoreOrder::DELIVERY_EASYPOST) {
            $delivery['name'] = $this->order->shipping['carrier'];
            $delivery['description'] = $this->order->shipping['service'];
            $delivery['rate'] = $this->order->shipping['rate'];
        } else {
            $delivery['name'] = $this->order->shipping['shipping_rate_name'];
            $delivery['description'] = $this->order->shipping['shipping_rate_description'];
            $delivery['rate'] = $this->order->shipping['price'];
        }

        $isUk = $this->countryService->matchCountryCode($this->order->customer->shipping_address['country'] ?? null, CountryService::COUNTRY_CODES['UK']);

        return view('marketplace.emails.order-confirmation-seller', [
            'order_no' => $this->order->order_no,
            'order_uuid' => $this->order->uuid,
            'name' => $this->order->customer->name,
            'order' => $this->order,
            'delivery' => $delivery,
            'isUk' => $isUk,
        ])->render();
    }

    protected function buildManualFulfillmentCompleted(): string
    {
        return view('marketplace.emails.manually-fullfilled', [
            'order_no' => $this->order->order_no,
            'order_uuid' => $this->order->uuid,
            'name' => $this->order->customer->name,
            'order' => $this->order,
            'user' => $this->order->customer,
        ])->render();
    }

    protected function buildFulfillmentCompleted(): string
    {
        // $this->identifyCurrency($this->order->customer);

        // $totals = (new AmountService($this->order))->orderTotal();

        // $delivery = $this->delivery($this->order, $totals['delivery']);

        // return view('marketplace.emails.shipping-label-fulfilled', [
        //     'estimated_shipping_date' => $delivery['description'], //TODO: add estimated shipping date
        //     'currency' => $this->currency,
        //     'order' => $this->order,
        //     'customer_name' => $this->order->customer->name,
        // ])->render();
        $this->order->load(['customer', 'store.address', 'items.product', 'easypost']);

        $wasPreviousVoided = json_decode($this->timeline->additional_data ?? null, true)['wasPreviousVoided'] ?? false;

        // if any of the shipping labels have been voided, show the new shipping label email
        if ($wasPreviousVoided) {
            return view('marketplace.emails.new-shipping-label', [
                'user' => $this->order->customer,
                'order' => $this->order,
                'store' => $this->order->store,
                'shipmentDetails' => json_decode($this->timeline->additional_data ?? null, true),
            ])->render();
        }

        return view('marketplace.emails.shipping-label-fulfilled', [
            'user' => $this->order->customer,
            'order' => $this->order,
            'store' => $this->order->store,
            'shipmentDetails' => $this->order->easypost->shipment,
        ])->render();
    }

    protected function buildFulfillmentVoided(): string
    {
        return view('marketplace.emails.shipping-label-voided', [
            'user' => $this->order->store->seller,
            'orderId' => $this->order->order_no,
        ])->render();
    }
}
