<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCustomizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'description' => $this->description,
            'limit' => $this->limit,
            'attach_file' => $this->attach_file,
            'description_enabled' => $this->description_enabled,
            'enabled' => 1,
        ];
    }
}
