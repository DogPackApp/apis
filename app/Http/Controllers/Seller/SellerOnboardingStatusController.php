<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Misc\OnboardingResource;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class SellerOnboardingStatusController extends Controller
{
    #[OA\Get(
        path: '/api/seller/onboarding/status',
        summary: "Get the authenticated seller's onboarding progress",
        security: [['sellerAuth' => []]],
        tags: ['Onboarding'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Onboarding status',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Onboarding')])
            ),
            new OA\Response(response: 204, description: "Onboarding hasn't started yet (no row exists)"),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
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
