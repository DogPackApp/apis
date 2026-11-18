<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Http\Resources\Review\ReportListResource;
use App\Models\Review\ItemReviewReportReason;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportListController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return ReportListResource::collection(ItemReviewReportReason::query()->get());
    }
}
