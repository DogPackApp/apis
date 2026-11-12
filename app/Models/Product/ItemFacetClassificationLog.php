<?php

namespace App\Models\Product;

use App\Models\Concerns\ConvertTimeZone;
use Illuminate\Database\Eloquent\Model;

class ItemFacetClassificationLog extends Model
{
    use ConvertTimeZone;

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
