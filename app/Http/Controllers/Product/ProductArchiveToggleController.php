<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Item;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class ProductArchiveToggleController extends Controller
{
    #[OA\Put(
        path: '/api/seller/product/{uuid}/archive',
        summary: 'Toggle a product between archived and draft',
        description: 'ACTIVE or DRAFT items move to ARCHIVED. ARCHIVED items move back to DRAFT.',
        security: [['sellerAuth' => []]],
        tags: ['Product'],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Toggled'),
            new OA\Response(response: 404, description: 'Not found, or belongs to another store'),
            new OA\Response(response: 422, description: 'The toggle failed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(Request $request, Item $item): JsonResponse
    {
        $store = $request->user()->store;

        if (! $store || $item->store_id !== $store->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        DB::beginTransaction();

        try {
            if ($item->status === Item::STATUS[Item::STATUS_ACTIVE] || $item->status === Item::STATUS[Item::STATUS_DRAFT]) {
                $item->status = Item::STATUS[Item::STATUS_ARCHIVED];
            } else {
                $item->status = Item::STATUS[Item::STATUS_DRAFT];
            }

            $item->save();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            $message = app()->environment() !== 'production' ? $e->getMessage() : 'Something went wrong';

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([], Response::HTTP_OK);
    }
}
