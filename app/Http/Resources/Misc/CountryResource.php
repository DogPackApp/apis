<?php

namespace App\Http\Resources\Misc;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Country',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 38),
        new OA\Property(property: 'name', type: 'string', example: 'Canada'),
        new OA\Property(property: 'short_name', type: 'string', example: 'CA'),
        new OA\Property(property: 'currency_code', type: 'string', nullable: true, example: 'CAD'),
        new OA\Property(property: 'currency_name', type: 'string', nullable: true, example: 'Canadian Dollar'),
        new OA\Property(property: 'currency_symbol', type: 'string', nullable: true, example: 'CA$'),
        new OA\Property(property: 'is_display', type: 'integer', enum: [0, 1]),
        new OA\Property(property: 'flag', type: 'string', format: 'uri'),
    ]
)]
class CountryResource extends JsonResource
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
            'currency_code' => $this->currency_code,
            'currency_name' => $this->currency_name,
            'currency_symbol' => $this->currency_symbol,
            'is_display' => $this->is_display,
            'flag' => $this->flagUrl(),
        ];
    }

    private function flagUrl(): string
    {
        $assetsUrl = (string) config('services.website_assets_url');
        $parsed = parse_url($assetsUrl);
        $cdnBase = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? 'cdn-web-asset-1.dogpackapp.com');

        return $cdnBase.'/countryflags/'.strtolower((string) $this->short_name).'.webp';
    }
}
