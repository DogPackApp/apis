<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\StoreResource;
use App\Models\Seller\OnboardingStatus;
use App\Models\Store\Section;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class StoreCreateController extends Controller
{
    #[OA\Post(
        path: '/api/marketplace/store',
        summary: 'Create the store for the authenticated seller',
        security: [['sellerAuth' => []]],
        tags: ['Store'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['name', 'phone'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                        new OA\Property(property: 'image', type: 'string', format: 'binary', nullable: true),
                        new OA\Property(property: 'phone', type: 'string'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Store created'),
            new OA\Response(response: 422, description: 'Validation error, or the seller already has a store'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            if (Store::_storeExists()) {
                return response()->json(['errors' => 'Store Already Exists'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255', 'unique:stores'],
                'description' => ['nullable', 'string'],
                'image' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
                'phone' => ['required', 'string', 'min:10', 'max:100'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::beginTransaction();
            $store = new Store;
            if (Store::where('slug', Str::slug($request->input('name')))->exists()) {
                DB::rollBack();

                return response()->json(['errors' => 'This Store name has already been taken.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $store->name = $request->input('name');
            $store->slug = Str::slug($request->input('name'));
            $store->description = $request->input('description') ?? null;
            $store->image = $request->input('image');
            $store->seller_id = auth()->user()->id;
            $store->status = 0;
            $store->save();

            OnboardingStatus::where('seller_id', auth()->user()->id)->update([
                'store_id' => $store->id,
            ]);

            $section = new Section;
            $section->name = 'All';
            $section->slug = 'all';
            $section->store_id = $store->id;
            $section->is_visible = 1;
            $section->order = 0;
            $section->save();

            if ($request->file('image')) {
                $this->updateProfilePhoto($request->file('image'), $store);
            }

            $user = auth()->user();
            $user->phone = $request->input('phone');
            $user->save();

            DB::commit();

            return StoreResource::make($store)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (Exception $e) {
            DB::rollBack();
            if (app()->environment() !== 'production') {
                $message = $e->getMessage();
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['error' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public static function updateProfilePhoto($image, $store, $field = 'image'): void
    {
        $path = $image->store("upload/store/{$store->uuid}", 'mps3');

        Storage::disk('mps3')->setVisibility($path, 'public');

        $store->{$field} = $path;
        $store->update();
    }
}
