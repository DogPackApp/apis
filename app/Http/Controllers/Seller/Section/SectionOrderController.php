<?php

namespace App\Http\Controllers\Seller\Section;

use App\Http\Controllers\Controller;
use App\Models\Store\Section;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class SectionOrderController extends Controller
{
    public function __invoke(Request $request, Section $section): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'data' => ['required', 'array'],
            'data.*.uuid' => ['required', 'uuid'],
            'data.*.order' => ['required', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();

            foreach ($validator->validated()['data'] as $item) {
                Section::updateOrCreate(
                    ['uuid' => $item['uuid']],
                    [
                        'order' => $item['order'],
                        'store_id' => resolve(Store::class)->id,
                        'updated_at' => now(),
                    ]
                );
            }

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
