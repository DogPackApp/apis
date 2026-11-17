<?php

namespace App\Http\Resources\Analytics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TotalSalesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'month' => $this->month_name,
            'sales' => $this->total_sales / 100,
        ];
    }
}
