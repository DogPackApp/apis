<?php

namespace App\Http\Controllers\Seller\Policy;

use App\Http\Controllers\Controller;
use App\Models\Product\Item;
use App\Models\Store\Policy;
use App\Services\Store\PolicyService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class PolicyItemAddController extends Controller
{
    public PolicyService $policyService;

    public function __construct(PolicyService $policyService)
    {
        $this->policyService = $policyService;
    }

    public function __invoke(Request $request, Policy $policy): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array'],
            'items.*' => ['required', 'uuid', 'exists:items,uuid'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();

            $itemIds = Item::query()
                ->whereIn('uuid', $request->input('items.*'))
                ->pluck('id')
                ->unique()
                ->toArray();

            $this->policyService->updateItemPolicy($itemIds, $policy);

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
