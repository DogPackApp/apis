<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Models\Review\ItemReview;
use App\Models\Review\ItemReviewReport;
use App\Models\Review\ItemReviewReportReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request, ItemReview $itemReview): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason_id' => ['required', 'uuid'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $reason = ItemReviewReportReason::query()
            ->where('uuid', $validator->validated()['reason_id'])
            ->first();

        $report = new ItemReviewReport;
        $report->item_review_id = $itemReview->id;
        $report->dp_user_id = null;
        $report->item_review_report_reason_id = $reason->id;
        $report->save();

        return response()->json([], Response::HTTP_OK);
    }
}
