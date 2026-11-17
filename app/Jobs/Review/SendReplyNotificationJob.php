<?php

namespace App\Jobs\Review;

use App\Models\Product\Item;
use App\Models\Review\ItemReview;
use App\Models\Store\Store;
use App\Models\V2\Notification;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendReplyNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $replyNotificationData;

    /**
     * @param  array<string, mixed>  $replyNotificationData
     */
    public function __construct(array $replyNotificationData)
    {
        $this->replyNotificationData = $replyNotificationData;
    }

    public function handle(): void
    {
        try {
            $data = [
                'url' => '',
                'image' => '',
                'reference_type' => ItemReview::NOTIFICATION_TYPE,
                'product_uuid' => '',
                'store_uuid' => '',
            ];

            $item = Item::query()->select('uuid', 'title')->whereKey($this->replyNotificationData['item_id'])->first();
            if (! $item) {
                throw new Exception('Item not found');
            }

            $store = Store::query()->select('image', 'uuid', 'name')->whereKey($this->replyNotificationData['store_id'])->first();
            if (! $store) {
                throw new Exception('Store not found');
            }

            $data['url'] = 'https://app.dogpackapp.com/share-marketplace-product?slug='.Str::slug($item->title).'&id='.$item->uuid;
            $data['product_uuid'] = $item->uuid;
            $data['image'] = $store->image;
            $data['thumb'] = $store->image;
            $data['store_uuid'] = $store->uuid;

            Notification::sendNotification(
                $this->replyNotificationData['user_id'],
                '',
                ItemReview::NOTIFICATION_TYPE,
                'New reply to your review',
                $store->name.' replied to the review you left for a product. Tap to view.',
                $data
            );

            Log::info('Marketplace Review Reply notification sent to user: '.$this->replyNotificationData['user_id'], ['data' => $data]);
        } catch (Exception $e) {
            Log::error('Failed to send reply notification: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
