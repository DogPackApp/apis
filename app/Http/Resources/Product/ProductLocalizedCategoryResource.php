<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductLocalizedCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'name' => $this->name,
            'ht_code' => $this->ht_code,
            'order' => $this->order,
            'is_active' => $this->is_active,
            'type' => $this->type,
            'parent_id' => $this->parent_id,
            'id' => $this->id,
            'title' => $this->title,
            'parents' => $this->parents,
            'top_parent' => $this->top_parent,
            'full_path' => $this->full_path,
        ];
    }
}
