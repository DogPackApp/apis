<?php

namespace App\Events\Product;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportCSVErrorsEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcastQueue = 'mkt-high';

    public $sellerId;

    public $batchId;

    public $message;

    public function __construct($sellerId, $batchId, $message)
    {
        $this->batchId = $batchId;
        $this->sellerId = $sellerId;
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('seller.csv.'.$this->sellerId);
    }

    public function broadcastAs()
    {
        return 'import.csv.error';
    }

    public function broadcastWith()
    {
        return [
            'batch_id' => $this->batchId,
            'seller_id' => $this->sellerId,
            'message' => $this->message,
            'timestamp' => now()->toDateTimeString(),
        ];
    }
}
