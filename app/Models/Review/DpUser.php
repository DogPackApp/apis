<?php

namespace App\Models\Review;

use Illuminate\Database\Eloquent\Model;

class DpUser extends Model
{
    protected $table = 'dp_users';

    public $timestamps = false;

    protected $fillable = [
        'user_uuid',
        'username',
        'profile_picture',
        'profile_thumb',
    ];
}
