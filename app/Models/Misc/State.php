<?php

namespace App\Models\Misc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class State extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'id',
        'country_id',
        'short_name',
        'name',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
