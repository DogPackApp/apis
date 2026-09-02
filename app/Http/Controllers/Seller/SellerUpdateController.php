<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\Seller\SellerResource;
use App\Models\Seller\Seller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class SellerUpdateController extends Controller
{
    public function __invoke(Request $request, Seller $seller): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'min:10', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($seller->id !== $request->user()->id) {
            return response()->json(['errors' => [
                'others' => 'Action not allowed.',
            ]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $seller->first_name = $request->input('first_name');
        $seller->last_name = $request->input('last_name');
        if ($request->has('phone')) {
            $seller->phone = $request->input('phone');
        }

        $seller->update();

        return SellerResource::make($seller)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
