<?php

namespace App\Http\Resources\Misc;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Misc',
    description: 'Shared shape for currency and language list items. currency_code/currency_name/currency_symbol are always null here — neither the currencies nor languages table has those columns; this mirrors a resource backend-api reuses as-is for both lists.',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'currency_code', type: 'string', nullable: true),
        new OA\Property(property: 'currency_name', type: 'string', nullable: true),
        new OA\Property(property: 'currency_symbol', type: 'string', nullable: true),
    ]
)]
class MiscResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'currency_code' => $this->currency_code ?? null,
            'currency_name' => $this->currency_name ?? null,
            'currency_symbol' => $this->currency_symbol ?? null,
        ];
    }
}
