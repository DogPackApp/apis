<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Services\Product\ProductService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ProductDeleteController extends Controller
{
    public function __construct(private readonly ProductService $productService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source_id' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $this->productService->setProductAsDeleted($validator->validated());

            DB::commit();

            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (Exception $e) {
            DB::rollBack();

            $message = app()->environment() !== 'production'
                ? $e->getMessage()
                : 'Something went wrong';

            return response()->json([
                'errors' => [
                    'message' => $message,
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
