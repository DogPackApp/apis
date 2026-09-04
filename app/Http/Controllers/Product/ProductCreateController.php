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

class ProductCreateController extends Controller
{
    public function __construct(private readonly ProductService $productService) {}

    #[OA\Post(
        path: '/api/seller/product',
        summary: 'Create a product',
        description: "Validation only runs when status is ACTIVE — any other status (DRAFT, ARCHIVED) skips validation and stores the payload as-is, matching backend-api's behavior.",
        security: [['sellerAuth' => []]],
        tags: ['Product'],
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(
                response: 404,
                description: 'The seller has no store yet',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Store not found.')])
            ),
            new OA\Response(response: 422, description: 'Validation error, or the create failed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $store = $request->user()->store;

        if (! $store) {
            return response()->json(['message' => 'Store not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($request->has('status') && $request->get('status') == Item::STATUS_ACTIVE) {
            $validator = Validator::make($request->all(), ProductValidationRules::forCreate());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $product = $validator->validated();
        } else {
            $product = $request->all();
        }

        DB::beginTransaction();

        try {
            $this->productService->createProduct($store, $product);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            $message = app()->environment() !== 'production' ? $e->getMessage() : 'Something went wrong';

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([], Response::HTTP_CREATED);
    }
}
