<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Http\Resources\Review\ReviewResource;
use App\Services\Review\ReviewService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ReviewController extends Controller
{
    public function __invoke(Request $request, ReviewService $reviewService): AnonymousResourceCollection|JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'orderBy' => ['required', Rule::in(['recent', 'top'])],
                'type' => ['required', Rule::in(['all', 'verified'])],
                'rating' => ['required', Rule::in(['1', '2', '3', '4', '5', 'all'])],
                'media' => ['required', Rule::in(['all', 1, 0])],
                'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $query = $reviewService->allReviewsBy($validator->validated());
            $perPage = $request->input('per_page');

            if ($perPage && $query) {
                $reviews = $query?->paginate((int) $perPage);
            } else {
                $reviews = $query?->get();
            }

            return ReviewResource::collection($reviews ?? collect())
                ->additional($reviewService->overviewOf());
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
