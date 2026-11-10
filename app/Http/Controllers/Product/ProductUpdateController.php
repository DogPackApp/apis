<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductValidationRules;
use App\Models\Product\Item;
use App\Services\Product\ProductService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class ProductUpdateController extends Controller
{
    public function __construct(private readonly ProductService $productService) {}

    #[OA\Put(
        path: '/api/seller/product/{uuid}',
        summary: 'Update a product',
        description: "Validation only runs when status is ACTIVE — any other status skips validation and stores the payload as-is, matching backend-api's behavior.",
        security: [['sellerAuth' => []]],
        tags: ['Product'],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Updated'),
            new OA\Response(response: 404, description: 'Not found, or belongs to another store'),
            new OA\Response(response: 422, description: 'Validation error, or the update failed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(Request $request, Item $item): JsonResponse
    {
        $store = $request->user()->store;

        if (! $store || $item->store_id !== $store->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if ($request->has('status') && $request->get('status') == Item::STATUS_ACTIVE) {
            $validator = Validator::make($request->all(), ProductValidationRules::forUpdate());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $product = $validator->validated();
        } else {
            $product = $request->all();
        }

        DB::beginTransaction();

        try {
            $this->productService->updateProduct($store, $item, $product);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            $message = app()->environment() !== 'production' ? $e->getMessage() : 'Something went wrong';

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([], Response::HTTP_OK);
    }
}
