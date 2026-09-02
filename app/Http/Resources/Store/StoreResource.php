<?php

namespace App\Http\Resources\Store;

use App\Support\Timezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Store',
    properties: [
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'slug', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'image', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'cover_image', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'social_links', type: 'object', nullable: true),
        new OA\Property(property: 'status', type: 'integer'),
        new OA\Property(property: 'states', type: 'string', enum: ['PENDING', 'ACTIVE', 'INACTIVE']),
        new OA\Property(property: 'timezone', type: 'string', example: 'America/Toronto'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class StoreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'cover_image' => $this->cover_image,
            'social_links' => $this->social_links,
            'status' => $this->status,
            'states' => $this->states,
            'timezone' => $this->timezone,
            'updated_at' => Timezone::convert($this->updated_at, $this->timezone)?->format('Y-m-d H:i:s'),
            'created_at' => Timezone::convert($this->created_at, $this->timezone)?->format('Y-m-d H:i:s'),
        ];
    }
}
