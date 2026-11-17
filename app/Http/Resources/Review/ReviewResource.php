<?php

namespace App\Http\Resources\Review;

use App\Http\Resources\Product\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'item' => new ProductResource($this->whenLoaded('item')),
            'username' => $this->username,
            'rating' => $this->rating,
            'title' => $this->title,
            'comment' => $this->comment,
            'response' => $this->response,
            'is_visible' => $this->is_visible,
            'images' => ItemReviewImageResource::collection($this->whenLoaded('images')),
            'created_at' => $this->created_at,
            'ordered' => $this->store_order_id ? true : false,
        ];
    }
}
