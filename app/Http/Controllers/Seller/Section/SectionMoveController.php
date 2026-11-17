<?php

namespace App\Http\Controllers\Seller\Section;

use App\Http\Controllers\Controller;
use App\Models\Product\Item;
use App\Models\Store\Section;
use App\Services\Store\SectionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class SectionMoveController extends Controller
{
    public SectionService $sectionService;

    public function __construct(SectionService $sectionService)
    {
        $this->sectionService = $sectionService;
    }

    public function __invoke(Request $request, Section $section): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array'],
            'items.*' => ['required', 'uuid', 'exists:items,uuid'],
            'sections' => ['required', 'array'],
            'sections.*' => ['required', 'uuid', 'exists:sections,uuid'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();

            $moveToSections = $this->sectionService->findByUUIDs($request->input('sections.*'));

            $itemIds = Item::query()
                ->whereIn('uuid', $request->input('items.*'))
                ->pluck('id')
                ->unique()
                ->toArray();

            foreach ($moveToSections as $moveToSection) {
                $this->sectionService->updateItemsToSection($itemIds, $moveToSection);
            }

            $this->sectionService->removeItemsFromSection($itemIds, $section);

            DB::commit();

            return response()->json([], Response::HTTP_NO_CONTENT);
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
