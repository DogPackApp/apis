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
            $section->load([
                'items.category',
                'items.subscribeSaveSetting',
                'items.store.settings',
                'items.policy',
                'items.tags',
                'items.discount',
                'items.customization',
                'items.price',
                'items.medias',
                'items.media',
                'items.varyBy',
                'items.sections',
                'items.attributes.attributeOptions',
                'items.itemVariants.media',
                'items.itemVariants.attributesOption.attribute',
                'items.activeFacets',
            ]);

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
