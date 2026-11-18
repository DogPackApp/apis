<?php

namespace App\Http\Controllers\Seller\Section;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\StoreSectionResource;
use App\Models\Store\Section;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SectionCreateController extends Controller
{
    public function __invoke(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'is_visible' => ['sometimes', 'integer', 'in:1,0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();

            $sectionCount = Section::query()
                ->where('store_id', $store->id)
                ->latest()
                ?->first()
                ?->order ?? 0;

            $section = new Section;
            $section->name = $request->input('name');
            $section->slug = Str::slug($request->input('name'));
            $section->store_id = $store->id;
            $section->is_visible = $request->input('is_visible') ?? 0;
            $section->order = ++$sectionCount;
            $section->save();

            DB::commit();

            return StoreSectionResource::make($section)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
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
