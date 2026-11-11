<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SellerChatReport extends Model
{
    protected $table = 'seller_chat_reports';

    protected $fillable = [
        'uuid',
        'thread_uuid',
        'reason',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($report) {
            $report->uuid = Str::uuid();
        });
    }
}
