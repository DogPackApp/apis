<?php

namespace App\Http\Controllers\Seller\Gallery;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\GalleryResource;
use App\Models\Product\Gallery;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class GalleryController extends Controller
{
    public function __invoke(Request $request, Store $store): AnonymousResourceCollection|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => ['sometimes', 'string', 'nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $perPage = $request->get('per_page', 15);

            $galleries = Gallery::query()
                ->where('store_id', $store->id)
                ->when($request->has('search') && $request->get('search'), function ($query) use ($request) {
                    $query->where('name', 'like', "%{$request->get('search')}%");
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage)
                ->appends($request->all());

            return GalleryResource::collection($galleries);
        } catch (Exception $exception) {
            $message = app()->environment() !== 'production'
                ? $exception->getMessage()
                : 'Something went wrong';

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
