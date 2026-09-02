<?php

namespace App\Http\Resources\Store;

use App\Support\Timezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
