<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerUpdateRequest;
use App\Http\Resources\Seller\SellerResource;
use App\Models\Seller\Seller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SellerUpdateController extends Controller
{
    public function __invoke(SellerUpdateRequest $request, Seller $seller): JsonResponse
    {
        if ($seller->id !== $request->user()->id) {
            return response()->json(['errors' => [
                'others' => 'Action not allowed.',
            ]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $seller->fill($request->safe()->only(['first_name', 'last_name', 'phone']));
        $seller->save();

        return SellerResource::make($seller)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
