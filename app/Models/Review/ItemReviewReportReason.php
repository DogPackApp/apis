<?php

namespace App\Models\Review;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;

class ItemReviewReportReason extends Model
{
    use ConvertTimeZone;

    protected $fillable = [
        '*',
    ];
}
