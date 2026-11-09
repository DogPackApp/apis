<?php

namespace App\Http\Resources\Misc;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'State',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 66),
        new OA\Property(property: 'name', type: 'string', example: 'Ontario'),
        new OA\Property(property: 'short_name', type: 'string', example: 'ON'),
    ]
)]
class StateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->short_name,
        ];
    }
}
