<?php

namespace App\Http\Controllers\Seller\Section;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\StoreSectionResource;
use App\Models\Store\Section;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SectionController extends Controller
{
    public function __invoke(Request $request, Store $store): AnonymousResourceCollection|JsonResponse
    {
        try {
            $cacheKey = "store_sections_{$store->id}";

            $sections = Cache::rememberForever($cacheKey, function () use ($store) {
                return Section::query()
                    ->withCount(['items'])
                    ->where('store_id', $store->id)
                    ->orderBy('order')
                    ->get();
            });

            return StoreSectionResource::collection($sections);
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
