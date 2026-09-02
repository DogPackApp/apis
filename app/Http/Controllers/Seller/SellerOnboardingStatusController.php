<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Misc\OnboardingResource;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SellerOnboardingStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $onboarding = (new OnboardingService)->fetchOnboardingStatus(request()->user());

        if (! $onboarding) {
            return response()->json([], Response::HTTP_NO_CONTENT);
        }

        return OnboardingResource::make($onboarding)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
