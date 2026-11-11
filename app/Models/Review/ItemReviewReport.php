<?php

namespace App\Models\Review;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ItemReviewReport extends Model
{
    protected $fillable = [
        '*',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($seller) {
            $seller->uuid = Str::uuid();
        });
    }
}
