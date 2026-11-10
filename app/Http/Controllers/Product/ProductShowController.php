<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class ProductShowController extends Controller
{
    #[OA\Get(
        path: '/api/seller/product/{uuid}',
        summary: 'Show a single product',
        security: [['sellerAuth' => []]],
        tags: ['Product'],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The product',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')])
            ),
            new OA\Response(response: 404, description: 'Not found, or belongs to another store'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(Request $request, Item $item): JsonResponse
    {
        $store = $request->user()->store;

        if (! $store || $item->store_id !== $store->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $item->load([
            'category',
            'policy',
            'tags',
            'discount',
            'customization',
            'price',
            'medias',
            'media',
            'varyBy',
            'sections',
            'attributes.attributeOptions',
            'itemVariants.media',
            'itemVariants.attributesOption.attribute',
            'activeFacets',
        ]);

        return ProductResource::make($item)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
