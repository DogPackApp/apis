<?php

namespace App\Mail\Subscribe;

use App\Models\Order\MarketplaceSubscription;
use App\Models\Product\Item;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Tells a subscriber this Subscribe & Save cycle could not be placed. The reason
 * stays generic: chargeback blocks must not be named in the email.
 */
class SubscriptionPurchaseBlockedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $firstName;

    public string $productTitle;

    public string $dueDate;

    public int $frequencyDays;

    public function __construct(MarketplaceSubscription $subscription, Carbon $cycleDueAt)
    {
        $this->onQueue(config('queue.low'));

        $address = (array) $subscription->shipping_address;

        $this->firstName = (string) ($address['first_name'] ?? 'there');
        $this->productTitle = (string) (Item::on('mysql_reader')
            ->where('id', $subscription->item_id)
            ->value('title') ?? 'your subscription');
        $this->dueDate = $cycleDueAt->format('j M Y');
        $this->frequencyDays = (int) $subscription->frequency_days;
    }

    public function build(): self
    {
        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.subscription-purchase-blocked')
            ->subject("We couldn't place your Subscribe & Save order");
    }
}
