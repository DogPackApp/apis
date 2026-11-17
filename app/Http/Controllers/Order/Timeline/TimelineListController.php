<?php

namespace App\Http\Controllers\Order\Timeline;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\Order\TimelineResource;
use App\Models\Order\StoreOrder;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TimelineListController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request, StoreOrder $order): AnonymousResourceCollection|JsonResponse
    {
        try {
            $order->load(['timelines' => fn ($query) => $query->orderBy('created_at', 'desc')]);

            return TimelineResource::collection($order->timelines);
        } catch (Exception $e) {
            DB::rollBack();

            if (app()->environment() !== 'production') {
                $message = $e->getMessage();
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
