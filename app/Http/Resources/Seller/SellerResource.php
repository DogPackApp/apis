<?php

namespace App\Http\Resources\Marketplace\Seller;

use App\Http\Resources\Marketplace\Store\StoreResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
            'twofa_enabled' => $this->twofa_enabled,
            'store' =>  StoreResource::make($this->whenLoaded('store')),
            'updated_at' => ($this->updated_at instanceof \DateTimeInterface)
                ? $this->updated_at->format('Y-m-d H:i:s')
                : $this->updated_at,
            'created_at' => ($this->created_at instanceof \DateTimeInterface)
                ? $this->created_at->format('Y-m-d H:i:s')
                : $this->created_at,
            'is_master_login' => $request->user()
                ? $request->user()->tokenCan('master-login')
                : (isset($this->additional['is_master_login'])
                    ? $this->additional['is_master_login']
                    : false
                ),
        ];
    }
}
