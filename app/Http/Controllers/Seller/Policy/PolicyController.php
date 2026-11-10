<?php

namespace App\Http\Controllers\Seller\Policy;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\PolicyResource;
use App\Services\Store\PolicyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PolicyController extends Controller
{
    public function __invoke(Request $request, PolicyService $policyService): AnonymousResourceCollection
    {
        return PolicyResource::collection($policyService->fetchPoliciesByStore());
    }
}
