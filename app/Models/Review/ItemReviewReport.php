<?php

namespace App\Models\Review;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ItemReviewReport extends Model
{
    use ConvertTimeZone;

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
