<?php

namespace App\Http\Resources\Store;

use App\Models\Store\Packaging;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackagingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'type' => array_search($this->type, Packaging::PACKING_TYPE),
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'unit' => array_search($this->unit, Packaging::PACKING_UNIT),
            'empty_weight' => $this->empty_weight,
            'empty_unit' => array_search($this->empty_unit, Packaging::PACKING_WEIGHT),
            'is_default' => $this->is_default,
        ];
    }
}
