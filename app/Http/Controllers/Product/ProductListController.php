<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product\Category;
use App\Models\Product\Item;
use App\Models\Store\Store;
use App\Models\SubscribeSave\ItemSubscribeSaveSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class ProductListController extends Controller
{
    #[OA\Get(
        path: '/api/seller/product',
        summary: "List the authenticated seller's store's products",
        security: [['sellerAuth' => []]],
        tags: ['Product'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['ACTIVE', 'DRAFT', 'ARCHIVED', 'DELETED'])),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'sort_by', in: 'query', schema: new OA\Schema(type: 'string', enum: ['title'])),
            new OA\Parameter(name: 'sort_direction', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['DIGITAL', 'PHYSICAL'])),
            new OA\Parameter(name: 'subscribe_save', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated products',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Product'))])
            ),
            new OA\Response(
                response: 404,
                description: 'The seller has no store yet',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Store not found.')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $store = $request->user()->store;

        if (! $store) {
            return response()->json(['message' => 'Store not found.'], Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(array_keys(Item::STATUS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'category' => ['nullable', 'uuid'],
            'sort_by' => ['nullable', 'in:title'],
            'sort_direction' => ['nullable', 'required_with:sort_by', 'in:asc,desc'],
            'type' => ['nullable', Rule::in(array_keys(Item::TYPES))],
            'subscribe_save' => ['nullable', 'boolean'],
        ]);

        $filters = $validator->validated();

        $items = Item::query()
            ->with([
                'category',
                'subscribeSaveSetting',
                'store.settings',
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
            ])
            ->where('store_id', $store->id)
            ->when($request->boolean('subscribe_save'), function (Builder $query) use ($store) {
                $this->applySubscribeSaveListFilter($query, $store);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('title', 'like', '%'.$search.'%');
            })
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('status', Item::STATUS[$status]);
            })
            ->when($filters['type'] ?? null, function ($query, $type) {
                $query->where('type', Item::TYPES[$type]);
            })
            ->when($filters['category'] ?? null, function ($query, $categoryUuid) {
                $category = Category::query()->where('uuid', $categoryUuid)->first();
                $query->where('category_id', $category?->id);
            })
            ->when($filters['sort_by'] ?? null && $filters['sort_direction'] ?? null, function ($query) use ($filters) {
                $query->orderBy($filters['sort_by'], $filters['sort_direction']);
            }, function ($query) {
                $query->orderByDesc('created_at');
            })
            ->paginate($filters['per_page'] ?? 10);

        return ProductResource::collection($items)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Seller Subscribe & Save tab: products the seller can turn on or off.
     * Store must be on and must have a global discount. Category-on products
     * are included, plus any Admin force_on product. Digital and force_off stay out.
     * Seller-disabled products stay in the list.
     */
    private function applySubscribeSaveListFilter(Builder $query, Store $store): void
    {
        $store->loadMissing('settings');
        $settings = $store->settings;

        if ($settings?->subscribe_save_disabled || $settings?->subscribe_save_discount_percent === null) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where('type', '!=', Item::TYPES[Item::TYPE_DIGITAL])
            ->where(function (Builder $query) {
                $query->whereHas('category', function (Builder $category) {
                    $category->where('subscribe_save_enabled', true);
                })->orWhereHas('subscribeSaveSetting', function (Builder $setting) {
                    $setting->where('admin_override', ItemSubscribeSaveSetting::ADMIN_OVERRIDE_FORCE_ON);
                });
            })
            ->whereDoesntHave('subscribeSaveSetting', function (Builder $setting) {
                $setting->where('admin_override', ItemSubscribeSaveSetting::ADMIN_OVERRIDE_FORCE_OFF);
            });
    }
}
