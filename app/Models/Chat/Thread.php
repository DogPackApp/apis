<?php

namespace App\Models\Chat;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Thread extends Model
{
    use ConvertTimeZone;

    public function customer(): BelongsTo
    {
        return $this->belongsTo(ChatUser::class, 'customer_id', 'uuid');
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    public function unreadChats(): HasMany
    {
        return $this->hasMany(Chat::class, 'thread_uuid', 'uuid')
            ->whereColumn('sender_id', '!=', 'threads.seller_id')
            ->where('is_read', 0);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }
}
