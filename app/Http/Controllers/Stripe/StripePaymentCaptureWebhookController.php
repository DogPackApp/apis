<?php

namespace App\Http\Controllers\Stripe;

use App\Http\Controllers\Controller;
use App\Jobs\Order\ApplyDisputeStatusJob;
use App\Models\Order\StoreOrder;
use App\Models\Stripe\DisputeStripeLog;
use App\Services\StripeKeyService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class StripePaymentCaptureWebhookController extends Controller
{
    protected $stripe;

    public function __invoke(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $verified = StripeKeyService::verifyWebhook($payload, $sigHeader, 'wb_stripe_payment_capture');
            $event = $verified['event'];
            $this->stripe = new StripeClient($verified['config']['key']);
        } catch (\UnexpectedValueException $e) {
            Log::error('Webhook payload error: '.$e->getMessage());

            return response('Invalid payload', 400);
        } catch (\Throwable $e) {
            Log::error('Webhook signature verification failed: '.$e->getMessage());

            return response('Invalid signature', 400);
        }

        $type = $event->type;
        $obj = $event->data->object;

        if ($this->isDisputeEvent($type)) {
            $this->handleDispute($event);

            return response('OK', 200);
        }

        $paymentIntentId = $this->extractPaymentIntentId($obj);

        if (! $paymentIntentId) {
            Log::warning("No payment intent ID found for event type: {$type}");

            return response('OK', 200);
        }

        $order = StoreOrder::whereHas('paymentDetail', function ($query) use ($paymentIntentId) {
            $query->where('payment_id', $paymentIntentId);
        })->first();

        if (! $order) {
            $metadataOrderId = $obj->metadata->order_id ?? null;
            if ($metadataOrderId) {
                $order = StoreOrder::where('order_no', $metadataOrderId)->first();
            }

            if (! $order) {
                Log::warning("No orders found for payment_intent: {$paymentIntentId}");

                return response('OK', 200);
            }
        }

        switch ($type) {
            case 'payment_intent.succeeded':
                $this->OrderSuccess($order);
                break;
            case 'payment_intent.payment_failed':
                $this->OrderFailed($order);
                break;
        }

        return response('OK', 200);
    }

    protected function extractPaymentIntentId($obj)
    {
        if (isset($obj->object) && $obj->object === 'payment_intent' && ! empty($obj->id)) {
            return $obj->id;
        }

        if (! empty($obj->payment_intent)) {
            return $obj->payment_intent;
        }

        if (! empty($obj->id) && strpos($obj->id, 'pi_') === 0) {
            return $obj->id;
        }

        if (! empty($obj->charge->payment_intent)) {
            return $obj->charge->payment_intent;
        }

        return null;
    }

    protected function isDisputeEvent(string $type): bool
    {
        return in_array($type, [
            'charge.dispute.created',
            'charge.dispute.updated',
            'charge.dispute.funds_withdrawn',
            'charge.dispute.funds_reinstated',
            'charge.dispute.closed',
        ], true);
    }

    protected function handleDispute($event): void
    {
        $eventId = $event->id ?? null;
        if (! $eventId) {
            Log::warning('Dispute webhook missing event id', ['type' => $event->type ?? null]);

            return;
        }

        $dispute = $event->data->object;

        try {
            $log = DisputeStripeLog::query()->firstOrCreate(
                ['stripe_event_id' => $eventId],
                [
                    'event_type' => $event->type,
                    'dispute_id' => $dispute->id ?? null,
                    'payment_intent_id' => $this->stripeId($dispute->payment_intent ?? null),
                    'charge_id' => $this->stripeId($dispute->charge ?? null),
                    'amount' => isset($dispute->amount) ? (int) $dispute->amount : null,
                    'currency' => isset($dispute->currency) ? strtoupper((string) $dispute->currency) : null,
                    'reason' => $dispute->reason ?? null,
                    'status' => $dispute->status ?? null,
                    'store_order_ids' => null,
                    'payload' => $this->stripeToArray($event),
                ]
            );
        } catch (UniqueConstraintViolationException $e) {
            return;
        }

        if (! $log->wasRecentlyCreated) {
            return;
        }

        ApplyDisputeStatusJob::dispatch($log->id)
            ->delay(now()->addSeconds(30));
    }

    protected function stripeId($value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_object($value) && ! empty($value->id)) {
            return (string) $value->id;
        }

        return null;
    }

    protected function stripeToArray($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return json_decode(json_encode($value), true) ?? [];
    }

    protected function OrderSuccess($order)
    {
        DB::beginTransaction();
        try {
            $order->payment_status = StoreOrder::ORDER_PAYMENT_STATUS_PAID;
            $order->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating order payment status: '.$e->getMessage(), [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
            ]);
        }
    }

    protected function OrderFailed($order)
    {
        DB::beginTransaction();
        try {
            $order->payment_status = StoreOrder::ORDER_PAYMENT_STATUS_FAILED;
            $order->save();
            Log::warning("Order {$order->id} has a dispute created");
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error handling dispute: '.$e->getMessage());
        }
    }
}
