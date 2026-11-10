<?php

namespace App\Http\Controllers\Concerns;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

trait RespondsWithSellerJson
{
    protected function successResponse(mixed $message = null, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], $status);
    }

    protected function emptySuccessResponse(): JsonResponse
    {
        return response()->json([], Response::HTTP_NO_CONTENT);
    }

    protected function errorResponse(mixed $errors, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return response()->json([
            'errors' => $errors,
        ], $status);
    }

    protected function handleException(Exception $e): JsonResponse
    {
        Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

        $message = app()->environment('production')
            ? 'Something went wrong'
            : [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];

        return response()->json([
            'errors' => $message,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    protected function successResponseWithData(mixed $data = [], mixed $message = null, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
