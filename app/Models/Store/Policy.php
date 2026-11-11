<?php

namespace App\Models\Store;

use App\Models\Concerns\ConvertTimeZone;
use App\Models\Product\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Policy extends Model
{
    use ConvertTimeZone;

    protected $fillable = [
        'name',
        'is_return',
        'is_exchange',
        'is_default',
        'time',
        'store_id',
        'custom_text',
    ];

    protected function casts(): array
    {
        return [
            'is_return' => 'integer',
            'is_exchange' => 'integer',
            'is_default' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($policy): void {
            $policy->uuid = Str::uuid();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
