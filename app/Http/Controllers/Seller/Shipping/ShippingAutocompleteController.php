<?php

namespace App\Http\Controllers\Seller\Shipping;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ShippingAutocompleteController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'keyword' => ['required'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $keyword = urlencode($request->keyword);

            $sessionToken = auth()->user()->id ?? 123456789;

            $response = Http::get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
                'input' => $keyword,
                'key' => config('services.marketplace_google_api'),
                'language' => 'en',
                'sessiontoken' => $sessionToken,
                ...(session('userAddressDetail.short_name') ? ['components' => 'country:'.session('userAddressDetail.short_name')] : []),
            ]);

            $responseData = $response->json();

            if (! $response->successful() || ! isset($responseData['status']) || $responseData['status'] !== 'OK') {
                throw new Exception($response->json()['error_message'] ?? 'Unknown Error', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json($responseData, 200);
        } catch (Exception $e) {
            return response()->json([
                'errors' => [
                    'keywords' => $e->getMessage(),
                ],
            ], $e->getCode());
        }
    }
}
