<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\SubscribeSave\SubscribeSaveEligibilityService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SubscribeSaveAllowedPercentsController extends Controller
{
    public function __invoke(SubscribeSaveEligibilityService $eligibility): JsonResponse
    {
        return response()->json([
            'subscribe_save_allowed_percents' => $eligibility->allowedSellerPercents(),
        ], Response::HTTP_OK);
    }
}
