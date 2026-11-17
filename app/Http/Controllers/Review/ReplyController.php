<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Jobs\Review\SendReplyNotificationJob;
use App\Models\Review\ItemReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ReplyController extends Controller
{
    public function __invoke(Request $request, ItemReview $itemReview): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => ['required', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $itemReview->response = $validator->validated()['message'];
        $itemReview->save();

        if (isset($itemReview->dp_user_id) && ! empty($itemReview->dp_user_id)) {
            SendReplyNotificationJob::dispatchSync([
                'user_id' => $itemReview->dp_user_id,
                'item_id' => $itemReview->item_id,
                'store_id' => $itemReview->store_id,
            ]);
        }

        return response()->json([], Response::HTTP_OK);
    }
}
