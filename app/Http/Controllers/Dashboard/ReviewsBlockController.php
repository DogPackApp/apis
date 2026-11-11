<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Store\Store;
use App\Services\Review\ReviewService;
use Illuminate\Http\JsonResponse;

class ReviewsBlockController extends Controller
{
    public Store $store;

    public function __construct()
    {
        $this->store = resolve(Store::class);
    }

    public function __invoke(): JsonResponse
    {
        $reviews = (new ReviewService)->overviewOf();

        return response()->json([$reviews]);
    }
}
