<?php

namespace App\Mail\Order;

use App\Models\Order\ItemReview;
use App\Models\Order\StoreOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class OrderAskReviewMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;

    public $orderUuid;

    public $orders;

    public $summary = [];

    public $order;

    public $orderId;

    public function __construct($user, $orderUuid)
    {
        $this->onQueue(config('queue.low'));

        $this->user = $user;
        $this->orderUuid = $orderUuid;
    }

    /**
     * A review left during the 14 day wait must still stop this email, so the
     * check happens here, when the queued mail is sent, not when it was queued.
     */
    public function send($mailer)
    {
        $this->loadOrder();

        if ($this->order && $this->reviewableItems()->isEmpty()) {
            Log::info('Subscribe & Save review email skipped: product already reviewed', [
                'order_uuid' => $this->orderUuid,
            ]);

            return null;
        }

        return parent::send($mailer);
    }

    public function build()
    {
        $this->loadOrder();
        $this->order->setRelation('items', $this->reviewableItems());

        $this->orderId = $this->order->order_no;

        $summary = [
            'subtotal' => 0,
            'delivery' => 0,
            'tax_rate' => 0,
            'discount' => 0,
            'total' => 0,
        ];

        $billing = $this->order->orderBilling;

        $summary['subtotal'] += $billing->subtotal;
        $summary['delivery'] += $billing->shipping_rate;
        $summary['tax_rate'] += $billing->tax_rate;
        $summary['discount'] += $billing->discount;
        $summary['total'] += $billing->total;

        $this->summary = $summary;

        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.request-review')
            ->subject('How was your recent order from DogPack?');
    }

    private function loadOrder(): void
    {
        if ($this->order) {
            return;
        }

        $this->order = StoreOrder::query()
            ->with(['store', 'customer', 'items', 'orderBilling'])
            ->where('uuid', $this->orderUuid)
            ->first();
    }

    /**
     * One-time lines are always asked about. A Subscribe & Save line is asked
     * about after every order until this customer has reviewed that product,
     * and then never again.
     */
    private function reviewableItems(): Collection
    {
        return $this->order->items
            ->reject(fn ($item) => $this->isSubscriptionItem($item) && $this->alreadyReviewed((int) $item->product_id))
            ->values();
    }

    private function isSubscriptionItem($item): bool
    {
        return ! empty($item->is_subscribe_save) || ! empty($item->subscription_id);
    }

    private function alreadyReviewed(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        $email = $this->order->customer->email ?? null;
        $userId = $this->order->user_id ?? null;

        return ItemReview::query()
            ->where('item_id', $productId)
            ->where(function ($query) use ($email, $userId) {
                $query->whereRaw('0 = 1');

                if ($userId) {
                    $query->orWhere('dp_user_id', $userId);
                }

                if ($email) {
                    $query->orWhereHas('user', fn ($user) => $user->where('email', $email));
                    $query->orWhereHas('order.customer', fn ($customer) => $customer->where('email', $email));
                }
            })
            ->exists();
    }
}
