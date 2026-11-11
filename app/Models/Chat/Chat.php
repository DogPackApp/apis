<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Chat extends Model
{
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }
}
