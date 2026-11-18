<?php

namespace App\Http\Controllers\Seller\Shipping;

use App\Http\Controllers\Controller;
use App\Jobs\Store\ClearShippingCacheJob;
use App\Models\Product\Item;
use App\Models\Store\Packaging;
use App\Models\Store\ShippingZone;
use App\Models\Store\ShippingZoneRate;
use App\Models\Store\Store;
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
            'shipping.*.country.*' => ['required', 'integer', 'exists:marketplace_countries,id'],
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

            'shipping.*.shipping_zone_rates.*.item_uuids' => [
                'required_if:shipping.*.shipping_zone_rates.*.condition_type,ITEM',
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    if (is_array($value) && count($value) !== count(array_unique($value))) {
                        $fail("The {$attribute} field must not contain duplicate items.");
                    }
                },
            ],
            'shipping.*.shipping_zone_rates.*.item_uuids.*' => ['required', 'uuid'],

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

        [$shipping, $itemErrors] = $this->resolveItemUuids($validator->validated()['shipping']);

        if ($itemErrors !== []) {
            return response()->json(['errors' => $itemErrors], 422);
        }

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

            ShippingZone::forgetListCache(auth()->user()->store->id);

            ClearShippingCacheJob::dispatch(auth()->user()->store->id);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json(['errors' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([], Response::HTTP_CREATED);
    }

    /**
     * @return array{0: array, 1: array<string, string[]>}
     */
    private function resolveItemUuids(array $shipping): array
    {
        $uuids = collect($shipping)
            ->pluck('shipping_zone_rates.*.item_uuids')
            ->flatten()
            ->filter()
            ->unique()
            ->values();

        if ($uuids->isEmpty()) {
            return [$shipping, []];
        }

        $itemIds = Item::on('mysql_reader')
            ->where('store_id', resolve(Store::class)->id)
            ->whereIn('uuid', $uuids)
            ->pluck('id', 'uuid');

        $errors = [];

        foreach ($shipping as $zoneIndex => $zone) {
            foreach ($zone['shipping_zone_rates'] as $rateIndex => $rate) {
                if (empty($rate['item_uuids'])) {
                    continue;
                }

                $ids = [];

                foreach ($rate['item_uuids'] as $uuidIndex => $uuid) {
                    if (! isset($itemIds[$uuid])) {
                        $errors["shipping.{$zoneIndex}.shipping_zone_rates.{$rateIndex}.item_uuids.{$uuidIndex}"]
                            = ['The selected item is invalid.'];

                        continue;
                    }

                    $ids[] = $itemIds[$uuid];
                }

                unset($shipping[$zoneIndex]['shipping_zone_rates'][$rateIndex]['item_uuids']);
                $shipping[$zoneIndex]['shipping_zone_rates'][$rateIndex]['item_ids'] = $ids;
            }
        }

        return [$shipping, $errors];
    }
}
