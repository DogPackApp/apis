<?php

namespace App\Services\Order;

use App\Models\Order\Timeline;

class TimelineService
{
    protected string $event;

    protected int $storeOrderId;

    protected ?string $additionalData;

    protected ?string $email = null;

    public function for(string $event, int $storeOrderId, ?string $additionalData = null): TimelineService
    {
        $this->event = $event;
        $this->storeOrderId = $storeOrderId;
        $this->additionalData = $additionalData;

        return $this;
    }

    /**
     * Store the actual email HTML that was sent
     */
    public function withEmailContent(string $emailHtml): TimelineService
    {
        $this->email = $emailHtml;

        return $this;
    }

    /**
     * @param  $seconds
     *                  Add the above para if you want a delay in the timeline
     * @return $this
     */
    public function generate($seconds = null): TimelineService
    {
        Timeline::create([
            'event' => $this->event,
            'store_order_id' => $this->storeOrderId,
            'additional_data' => $this->additionalData,
            'email' => $this->email,
            'created_at' => $seconds > 0 ? now()->addSeconds($seconds) : now(),
        ]);

        return $this;
    }
}
