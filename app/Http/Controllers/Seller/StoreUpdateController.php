<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\StoreResource;
use App\Mail\SellerWelcomeEmail;
use App\Models\Store\Store;
use App\Models\Store\StoreInfo;
use App\Models\Store\StoreSetting;
use App\Services\SubscribeSave\SubscribeSaveEligibilityService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class StoreUpdateController extends Controller
{
    #[OA\Post(
        path: '/api/seller/store/{uuid}',
        summary: 'Update a store',
        security: [['sellerAuth' => []]],
        tags: ['Store'],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 201, description: 'Updated'),
            new OA\Response(response: 200, description: 'Only the Subscribe & Save percent was updated'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['sometimes', 'mimes:jpg,jpeg,png', 'max:1024'],
            'cover' => ['sometimes', 'mimes:jpg,jpeg,png', 'max:1024'],
            'country_id' => ['sometimes', 'integer', 'exists:marketplace_countries,id'],
            'language_id' => ['sometimes', 'integer', 'exists:languages,id'],
            'timezone' => ['sometimes', 'string'],
            'vat_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subscribe_save_discount_percent' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::in(app(SubscribeSaveEligibilityService::class)->allowedSellerPercents()),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            if ($request->exists('subscribe_save_discount_percent')) {
                $this->persistSubscribeSaveDiscount(
                    $store,
                    $request->input('subscribe_save_discount_percent')
                );
            }

            $onlySubscribeSaveDiscount = $request->exists('subscribe_save_discount_percent')
                && $request->keys() === ['subscribe_save_discount_percent'];

            if ($onlySubscribeSaveDiscount) {
                $store->load(['country', 'currency', 'language', 'storeInfo', 'settings']);

                return StoreResource::make($store)
                    ->response()
                    ->setStatusCode(Response::HTTP_OK);
            }

            if ($request->has('name')) {
                $isExists = Store::where('slug', Str::slug($request->input('name')))->where('id', '!=', $store->id)->exists();
                if ($isExists) {
                    return response()->json(['errors' => 'This Store name has already been taken.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $store->name = $request->input('name');
            }

            if ($request->has('description')) {
                $store->description = $request->input('description');
            }

            if ($request->file('image')) {
                StoreCreateController::updateProfilePhoto($request->file('image'), $store);
            }

            if ($request->file('cover')) {
                StoreCreateController::updateProfilePhoto($request->file('cover'), $store, 'cover_image');
            }

            if ($request->has('country_id')) {
                $store->country_id = $request->input('country_id');
            }

            if ($request->has('currency_id')) {
                $store->currency_id = $request->input('currency_id');
            }

            if ($request->has('language_id')) {
                $store->language_id = $request->input('language_id');
            }

            if ($request->has('social_links')) {
                $store->social_links = $request->input('social_links');
            }

            if ($request->has('timezone')) {
                $store->timezone = $request->input('timezone');
            }

            $countryId = $request->input('country_id', $store->country_id);
            if ((int) $countryId === 230) {
                if ($request->has('vat_number') && $request->input('vat_number')) {
                    StoreInfo::updateOrCreate(
                        ['store_id' => $store->id],
                        ['vat_number' => $request->input('vat_number')]
                    );
                } else {
                    StoreInfo::where('store_id', $store->id)->update(['vat_number' => null]);
                }
            }

            if ($store->status <= 0) {
                $seller = $store->loadMissing(['seller'])->seller;
                Mail::to($seller->email)->queue(new SellerWelcomeEmail($seller));
            }

            $store->status = 1;
            $store->save();

            $store->loadMissing(['country', 'currency', 'language', 'storeInfo', 'settings']);

            return StoreResource::make($store)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (Exception $e) {
            if (app()->environment() !== 'production') {
                $message = $e->getMessage();
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['error' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function persistSubscribeSaveDiscount(Store $store, mixed $percent): void
    {
        $setting = StoreSetting::query()->firstOrNew([
            'store_id' => $store->id,
        ]);
        $setting->subscribe_save_discount_percent = $percent;
        $setting->save();

        $eligibility = app(SubscribeSaveEligibilityService::class);
        $eligibility->putStoreSubscribeSaveCache(
            $store->id,
            $setting->subscribe_save_discount_percent,
            $setting->dogpack_subscribe_save_first_order_percent
        );
    }
}
