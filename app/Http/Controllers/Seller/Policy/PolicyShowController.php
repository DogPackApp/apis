<?php

namespace App\Http\Controllers\Seller\Policy;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\PolicyResource;
use App\Models\Store\Policy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PolicyShowController extends Controller
{
    public function __invoke(Request $request, Policy $policy): JsonResponse
    {
        return PolicyResource::make($policy)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
