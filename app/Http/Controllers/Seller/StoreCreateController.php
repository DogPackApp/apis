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
use Symfony\Component\HttpFoundation\Response;

class StoreCreateController extends Controller
{
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
