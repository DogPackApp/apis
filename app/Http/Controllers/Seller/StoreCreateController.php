<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OnboardingStep;
use App\Http\Controllers\Controller;
use App\Http\Requests\Store\StoreCreateRequest;
use App\Http\Resources\Store\StoreResource;
use App\Mail\SellerWelcomeEmail;
use App\Models\Store\Store;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class StoreCreateController extends Controller
{
    #[OA\Post(
        path: '/api/seller/store',
        summary: 'Create the store for the authenticated seller',
        description: 'One store per seller. On success, marks the store_setting onboarding step complete and sends a welcome email.',
        security: [['sellerAuth' => []]],
        tags: ['Store'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: "Ada's Widgets"),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'image', type: 'string', nullable: true),
                    new OA\Property(property: 'cover_image', type: 'string', nullable: true),
                    new OA\Property(property: 'social_links', type: 'object', nullable: true),
                    new OA\Property(property: 'timezone', type: 'string', nullable: true, example: 'America/Toronto'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Store created',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Store')])
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error, or the seller already has a store',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Store already exists.')])
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function __invoke(StoreCreateRequest $request, OnboardingService $onboardingService): JsonResponse
    {
        $seller = $request->user();

        if ($seller->store) {
            return response()->json(['message' => 'Store already exists.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $store = DB::transaction(function () use ($request, $seller): Store {
            $store = new Store($request->validated());
            $store->seller_id = $seller->id;
            $store->status = 1;
            $store->states = Store::STATES_ACTIVE;
            $store->save();

            return $store;
        });

        $onboardingService->complete($seller, OnboardingStep::StoreSetting);

        Mail::to($seller->email)->send(new SellerWelcomeEmail($seller));

        return StoreResource::make($store)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
