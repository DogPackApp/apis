<?php

namespace App\Http\Controllers\Seller\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\PackagingResource;
use App\Models\Store\Packaging;
use App\Models\Store\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PackagingShowController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $packaging = Packaging::query()
            ->where('store_id', resolve(Store::class)->id)
            ->latest()
            ->get();

        return PackagingResource::collection($packaging);
    }
}
