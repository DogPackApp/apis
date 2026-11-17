<?php

namespace App\Http\Controllers\Seller\Section;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\StoreSectionResource;
use App\Models\Store\Section;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SectionUpdateController extends Controller
{
    public function __invoke(Request $request, Section $section): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();

            $section->name = $request->input('name');
            $section->slug = Str::slug($request->input('name'));
            $section->save();

            DB::commit();

            return StoreSectionResource::make($section)
                ->response()
                ->setStatusCode(Response::HTTP_OK);
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
