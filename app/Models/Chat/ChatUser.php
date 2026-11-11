<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ChatUser extends Model
{
    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'email',
        'user_id',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }
}
