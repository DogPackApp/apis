<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\Subscribe\PricingResource;
use App\Models\Subscribe\Pricing;
use Illuminate\Http\Request;

class StripePricingController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request)
    {
        $prices = Pricing::where('status', '1')->get();

        return PricingResource::collection($prices);
    }
}
