<?php

namespace App\Http\Controllers\Seller\Section;

use App\Http\Controllers\Controller;
use App\Models\Store\Section;
use App\Services\Store\SectionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SectionDeleteController extends Controller
{
    public SectionService $sectionService;

    public function __construct(SectionService $sectionService)
    {
        $this->sectionService = $sectionService;
    }

    public function __invoke(Request $request, Section $section): JsonResponse
    {
        try {
            DB::beginTransaction();

            $section->loadCount(['items']);

            if ($section->items_count > 0) {
                $section->items()->detach();
            }

            $section->delete();

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
