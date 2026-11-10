<?php

namespace App\Http\Controllers\Order\Timeline;

use App\Models\Order\Timeline;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TimelineController
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => Timeline::list(),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            if (app()->environment() !== 'production') {
                $message = $e->getMessage();
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
