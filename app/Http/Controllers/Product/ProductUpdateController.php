<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductValidationRules;
use App\Models\Product\Item;
use App\Models\SubscribeSave\ItemSubscribeSaveSetting;
use App\Services\Product\ProductService;
use App\Services\SubscribeSave\SubscribeSaveEligibilityService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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

        $subscribeSave = null;
        if ($request->exists('subscribe_save')) {
            $subscribeSave = $this->validateSubscribeSave($request, $item);
            if ($subscribeSave instanceof JsonResponse) {
                return $subscribeSave;
            }
        }

        $onlySubscribeSave = is_array($subscribeSave)
            && ! $request->has('status')
            && ! $request->has('about');

        if ($onlySubscribeSave) {
            $this->persistSubscribeSave($item, $subscribeSave);

            return response()->json([], Response::HTTP_OK);
        }

        if ($request->has('status') && $request->get('status') == Item::STATUS_ACTIVE) {
            $validator = Validator::make($request->all(), ProductValidationRules::forUpdate());

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $product = $validator->validated();
        } else {
            $product = $request->except('subscribe_save');
        }

        DB::beginTransaction();

        try {
            if (is_array($subscribeSave)) {
                $this->persistSubscribeSave($item, $subscribeSave);
            }

            $this->productService->updateProduct($store, $item, $product);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            $message = app()->environment() !== 'production' ? $e->getMessage() : 'Something went wrong';

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([], Response::HTTP_OK);
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function validateSubscribeSave(Request $request, Item $item): array|JsonResponse
    {
        $allowed = app(SubscribeSaveEligibilityService::class)->allowedSellerPercents();

        $validator = Validator::make($request->all(), [
            'subscribe_save' => ['required', 'array'],
            'subscribe_save.seller_disabled' => ['sometimes', 'boolean'],
            'subscribe_save.discount_percent' => ['sometimes', 'nullable', 'integer', Rule::in($allowed)],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $validator->validated()['subscribe_save'];
        if (! array_key_exists('seller_disabled', $data) && ! array_key_exists('discount_percent', $data)) {
            return response()->json([
                'errors' => ['subscribe_save' => ['Provide seller_disabled and/or discount_percent.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (array_key_exists('discount_percent', $data) && $data['discount_percent'] !== null) {
            $item->loadMissing('store.settings');
            if ($item->store?->settings?->subscribe_save_discount_percent === null) {
                return response()->json([
                    'errors' => [
                        'subscribe_save.discount_percent' => ['Set the store global discount before a product discount.'],
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistSubscribeSave(Item $item, array $data): void
    {
        $setting = ItemSubscribeSaveSetting::query()->firstOrNew([
            'item_id' => $item->id,
        ]);

        if (array_key_exists('seller_disabled', $data)) {
            $setting->seller_disabled = (bool) $data['seller_disabled'];
        }

        if (array_key_exists('discount_percent', $data)) {
            $setting->discount_percent = $data['discount_percent'];
        }

        $setting->save();
    }
}
