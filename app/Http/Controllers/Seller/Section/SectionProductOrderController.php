<?php

namespace App\Http\Controllers\Seller\Section;

use App\Http\Controllers\Controller;
use App\Models\Product\Item;
use App\Models\Store\Section;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class SectionProductOrderController extends Controller
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
                // TODO: Currently the FE is sending only one time: so this query is fine for now
                // Later when we enhance it to bulk reordering then we need to optimize this.
                $current = Item::where('uuid', $item['uuid'])->firstOrFail();

                $oldOrder = $section->items()->find($current->id)->pivot->order;

                if ($item['order'] < $oldOrder) {
                    $section->items()
                        ->wherePivotBetween('order', [$item['order'], $oldOrder - 1])
                        ->increment('order');
                } else {
                    $section->items()
                        ->wherePivotBetween('order', [$oldOrder + 1, $item['order']])
                        ->decrement('order');
                }

                $section->items()->updateExistingPivot($current->id, [
                    'order' => $item['order'],
                ]);
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
