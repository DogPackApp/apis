<?php

namespace App\Http\Resources\Store;

use App\Models\Product\Item;
use App\Models\Store\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreSectionResource extends JsonResource
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
            'is_visible' => $this->is_visible,
            'order' => $this->order,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(function (Item $item): array {
                $media = $item->relationLoaded('media') ? $item->media : null;

                return [
                    'uuid' => $item->uuid,
                    'title' => $item->title,
                    'media_url' => $media?->url,
                    'media' => $media === null ? null : [
                        'uuid' => $media->uuid,
                        'url' => $media->url,
                        'type' => $media->type,
                        'name' => $media->name,
                        'order' => $media->pivot?->order ?? $media->order ?? null,
                    ],
                    'sections' => $item->relationLoaded('sections')
                        ? $item->sections->map(fn (Section $section): array => [
                            'uuid' => $section->uuid,
                            'name' => $section->name,
                            'slug' => $section->slug,
                            'is_visible' => $section->is_visible,
                        ])->values()
                        : [],
                ];
            })->values()),
            'items_count' => $this->when(
                ! is_null($this->items_count),
                $this->items_count
            ),
        ];
    }
}
