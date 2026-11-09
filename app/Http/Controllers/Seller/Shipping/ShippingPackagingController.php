<?php

namespace App\Http\Controllers\Seller\Shipping;

use App\Http\Controllers\Controller;
use App\Models\Product\Item;
use App\Models\Store\Packaging;
use App\Models\Store\ShippingZoneRate;
use App\Services\Cache\CacheService;
use App\Services\OnboardingService;
use App\Services\Store\PackagingService;
use App\Services\Store\ShippingService;
use App\Services\Store\StoreFreeShippingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ShippingPackagingController extends Controller
{
    protected CacheService $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    public function __invoke(Request $request, ShippingService $shippingService, PackagingService $packagingService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shipping' => ['required', 'array', 'min:1'],
            'shipping.*.uuid' => ['nullable', 'uuid'],
            'shipping.*.name' => ['required', 'string', 'max:255'],
            'shipping.*.country' => ['required', 'array'],
            'shipping.*.country.*' => ['required', 'integer', 'exists:countries,id'],
            'shipping.*.excluded_states' => ['nullable', 'array'],
            'shipping.*.excluded_states.*' => ['array'],
            'shipping.*.excluded_states.*.*' => ['integer'],

            'shipping.*.shipping_zone_rates' => ['required', 'array'],
            'shipping.*.shipping_zone_rates.*.shipping_type' => ['required', 'integer', 'in:1,2'],
            'shipping.*.shipping_zone_rates.*.shipping_rates' => [
                'required_if:shipping.*.shipping_zone_rates.*.shipping_type,1',
                'nullable',
                'array',
            ],
            'shipping.*.shipping_zone_rates.*.price' => [
                'required_if:shipping.*.shipping_zone_rates.*.shipping_type,1',
                'nullable',
                'numeric',
                'min:0',
            ],
            'shipping.*.shipping_zone_rates.*.condition_type' => ['nullable', Rule::in(array_keys(ShippingZoneRate::ZONE_CONDITION_TYPE))],
            'shipping.*.shipping_zone_rates.*.low_value' => [
                'required_if:shipping.*.shipping_zone_rates.*.condition_type,PRICE',
                'nullable',
                'numeric',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $conditionTypeKey = str_replace('low_value', 'condition_type', $attribute);
                    $conditionType = request()->input($conditionTypeKey);

                    if (in_array($conditionType, array_keys(ShippingZoneRate::ZONE_CONDITION_TYPE)) && $value === null) {
                        $fail('The '.$attribute.' field is required when condition type is PRICE.');
                    }
                },
            ],
            'shipping.*.shipping_zone_rates.*.high_value' => [
                'nullable',
                'numeric',
                'gte:shipping.*.shipping_zone_rates.*.low_value',
            ],
            'shipping.*.shipping_zone_rates.*.weight' => [
                'required_if:shipping.*.shipping_zone_rates.*.condition_type,WEIGHT',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $conditionTypeKey = str_replace('weight', 'condition_type', $attribute);
                    $conditionType = data_get(request()->all(), $conditionTypeKey);

                    if ($conditionType === 'WEIGHT') {
                        $validWeights = array_keys(Item::ITEM_WEIGHT);
                        if (! in_array($value, $validWeights)) {
                            $fail("The {$attribute} must be one of: ".implode(', ', $validWeights));
                        }
                    }
                },
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $conditionTypeKey = str_replace('weight', 'condition_type', $attribute);
                    $conditionType = data_get(request()->all(), $conditionTypeKey);

                    if ($conditionType === 'WEIGHT' && $value === null) {
                        $fail("The {$attribute} field is required when condition type is WEIGHT.");
                    }
                },
            ],

            'shipping.*.shipping_zone_rates.*.shipping_rates_custom' => ['nullable', 'required_if:shipping.*.shipping_zone_rates.shipping_rates,custom', 'array'],
            'shipping.*.shipping_zone_rates.*.shipping_rates_custom.name' => ['nullable', 'required_if:shipping.*.shipping_zone_rates.shipping_rates,custom', 'string', 'max:255'],
            'shipping.*.shipping_zone_rates.*.shipping_rates_custom.description' => ['required_if:shipping.*.shipping_zone_rates.shipping_rates,custom', 'string', 'max:500'],

            'packaging' => ['required', 'array'],
            'packaging.*.uuid' => ['nullable', 'uuid'],
            'packaging.*.name' => ['required', 'string'],
            'packaging.*.type' => ['required', Rule::in(array_keys(Packaging::PACKING_TYPE))],
            'packaging.*.length' => ['required', 'numeric', 'gt:0'],
            'packaging.*.width' => ['required', 'numeric', 'gt:0'],
            'packaging.*.height' => ['required', 'numeric', 'gt:0'],
            'packaging.*.unit' => ['required', Rule::in(array_keys(Packaging::PACKING_UNIT))],
            'packaging.*.empty_weight' => ['required', 'numeric', 'gte:0'],
            'packaging.*.empty_unit' => ['required', Rule::in(array_keys(Packaging::PACKING_WEIGHT))],
            'packaging.*.is_default' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $shipping = $validator->validated()['shipping'];

        DB::beginTransaction();

        try {
            $shippingService->deleteShipping();
            $shippingService->createShippingZones($shipping);

            $packagingService->create($validator->validated());

            $this->cacheService->forgetAllByPattern('*'.StoreFreeShippingService::FREE_SHIPPING_CACHE_KEY_PREFIX.'*');
            $this->cacheService->forgetAllByPattern('*'.StoreFreeShippingService::BEST_SHIPPING_RATE_CACHE_KEY_PREFIX.auth()->user()->store->id.'*');
            $this->cacheService->forgetAllByPattern('*api_stores_list_*');
            $this->cacheService->forgetAllByPattern('*api_product_list_*');

            (new OnboardingService)->completeShipping(auth()->user());

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json(['errors' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([], Response::HTTP_CREATED);
    }
}
