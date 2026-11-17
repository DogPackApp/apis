<?php

namespace App\Mail\Chat;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChatNewMessage extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;

    public $messageData;

    public $receiverType;

    public $store;

    public function __construct($name, $messageData, $receiverType, $store)
    {
        $this->onQueue(config('queue.low'));
        $this->name = $name;
        $this->messageData = $messageData;
        $this->receiverType = $receiverType;
        $this->store = $store;
    }

    public function build()
    {
        return $this->from(config('mail.marketplace_from.address'), config('mail.marketplace_from.name'))
            ->view('marketplace.emails.new-message')
            ->subject("New Message from {$this->messageData->name}");
    }
}
