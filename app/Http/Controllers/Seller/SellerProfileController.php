<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Seller\SellerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SellerProfileController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return SellerResource::make($request->user())
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
