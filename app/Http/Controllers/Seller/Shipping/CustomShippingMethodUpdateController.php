<?php

namespace App\Http\Controllers\Seller\Shipping;

use App\Http\Controllers\Controller;
use App\Services\Store\ShippingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class CustomShippingMethodUpdateController extends Controller
{
    public function __invoke(Request $request, ShippingService $shippingService, string $shippingRateUuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $updatedShippingMethod = $shippingService->updateCustomShippingMethod(
                $shippingRateUuid,
                $validator->validated()['name'],
                $validator->validated()['description']
            );

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json(['errors' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json($updatedShippingMethod->only(['uuid', 'name', 'description']), Response::HTTP_OK);
    }
}
