<?php

namespace App\Events\Product;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportCSVProgressEvent implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcastQueue = 'mkt-high';

    public $sellerId;

    public $message;

    public $batchId;

    public function __construct($sellerId, $message, $batchId)
    {
        $this->sellerId = $sellerId;
        $this->message = $message;
        $this->batchId = $batchId;
    }

    // Define which channel to broadcast on
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('seller.csv.'.$this->sellerId);
    }

    public function broadcastAs(): string
    {
        return 'import.csv.progress';
    }

    public function broadcastWith(): array
    {
        return [
            'batch_id' => $this->batchId,
            'seller_id' => $this->sellerId,
            'message' => $this->message,
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
