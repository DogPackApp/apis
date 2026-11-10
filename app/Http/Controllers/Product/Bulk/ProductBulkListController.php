<?php

namespace App\Http\Controllers\Product\Bulk;

use App\Http\Controllers\Controller;
use App\Http\Resources\Product\ProductBulkEditResource;
use App\Models\Product\Item;
use App\Models\Store\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ProductBulkListController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'products' => ['required', 'array'],
            'products.*' => ['required', 'uuid', 'exists:items,uuid'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $items = Item::query()
            ->with([
                'medias', 'category', 'sections', 'policy', 'price',
                'customization', 'tags', 'discount',
                'attributes.attributeOptions', 'itemVariants.attributesOption.attribute',
                'varyBy', 'itemVariants.media',
            ])
            ->where('store_id', resolve(Store::class)->id)
            ->whereIn('uuid', $validator->validated()['products'])
            ->get();

        return ProductBulkEditResource::collection($items);
    }
}
