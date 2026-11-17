<?php

namespace App\Jobs\Order;

use App\Models\Order\EasyPostLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EasyPostLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $logData;

    public function __construct(array $logData)
    {
        $this->logData = $logData;
    }

    public function handle(): void
    {
        EasyPostLog::insert([
            'description' => $this->logData['data']['description'],
            'data' => json_encode($this->logData['data']),
            'created_at' => $this->logData['data']['created_at'],
        ]);
    }
}
