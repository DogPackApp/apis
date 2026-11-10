<?php

namespace App\Http\Controllers\Seller\Section;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\StoreSectionResource;
use App\Models\Store\Section;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SectionItemsController extends Controller
{
    public function __invoke(Request $request, Section $section): JsonResponse
    {
        try {
            $section->load(['items.sections', 'items.media']);

            return StoreSectionResource::make($section)
                ->response()
                ->setStatusCode(Response::HTTP_OK);
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
