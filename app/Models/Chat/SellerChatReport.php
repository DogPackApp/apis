<?php

namespace App\Models\Chat;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SellerChatReport extends Model
{
    use ConvertTimeZone;

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
