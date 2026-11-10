<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ItemFacetClassificationLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'item_id',
        'prompts',
        'usage',
        'cost',
        'duration_ms',
        'response',
    ];

    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
            'prompts' => 'array',
            'usage' => 'array',
            'cost' => 'float',
            'duration_ms' => 'integer',
            'response' => 'array',
        ];
    }
}
