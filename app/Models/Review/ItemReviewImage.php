<?php

namespace App\Models\Review;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ItemReviewImage extends Model
{
    protected $guarded = [];

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    const TYPE_IMAGE = 'image';

    const TYPE_VIDEO = 'video';

    const PROCESSED_PENDING = '0';

    const PROCESSED_DONE = '1';

    protected $casts = [
        'processed' => 'string',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
