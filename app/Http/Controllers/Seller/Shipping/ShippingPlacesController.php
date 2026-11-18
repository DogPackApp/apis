<?php

namespace App\Http\Controllers\Seller\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Resources\Store\ShippingAddressResource;
use App\Mail\ShippingAddressInvalid;
use App\Models\Store\ShippingAddress;
use App\Models\Store\Store;
use App\Services\Store\EasyPostService;
use EasyPost\Exception\Api\InvalidRequestException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ShippingPlacesController extends Controller
{
    public function __construct(public EasyPostService $easyPostService) {}

    public function __invoke(Request $request): JsonResponse|array
    {
        $shipping = null;

        try {
            $validator = Validator::make($request->all(), [
                'place_id' => ['required'],
                'type' => ['sometimes', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $sessionToken = auth()->user()->id ?? 123456789;

            $response = Http::get('https://maps.googleapis.com/maps/api/place/details/json', [
                'placeid' => $request->place_id,
                'fields' => 'formatted_address,geometry,address_components',
                'key' => config('services.marketplace_google_api'),
                'language' => 'en',
                'sessiontoken' => $sessionToken,
            ]);

            $responseData = $response->json();

            if (! $response->successful() || ! isset($responseData['status']) || $responseData['status'] !== 'OK') {
                throw new Exception($response->json()['error_message'] ?? 'Unknown Error', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $addressComponents = $responseData['result']['address_components'];

            $store = app(Store::class);

            $addressData = [
                'store_id' => $store->id,
                'country' => $this->getAddressComponent($addressComponents, 'country'),
                'province' => $this->getAddressComponent($addressComponents, 'administrative_area_level_1'),
                'city' => $this->extractCity($addressComponents),
                'latitude' => $responseData['result']['geometry']['location']['lat'],
                'longitude' => $responseData['result']['geometry']['location']['lng'],
                'address' => $responseData['result']['formatted_address'],
                'street1' => $this->getAddressComponent($addressComponents, 'street_number').' '.$this->getAddressComponent($addressComponents, 'route'),
                'street2' => $this->getAddressComponent($addressComponents, 'sublocality_level_1'),
                'zipcode' => $this->getAddressComponent($addressComponents, 'postal_code'),
            ];

            if (empty(trim($addressData['zipcode'])) || empty(trim($addressData['street1']))) {
                return response()->json([
                    'errors' => [
                        'address' => 'Incomplete address information: street or postal code is missing.',
                        'code' => 422,
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (isset($validator->validate()['type']) && $validator->validate()['type'] === 'billing') {
                $countryCode = $store->refresh()->loadMissing(['country'])?->country ?? 'Canada';
                $addressData['country_code'] = $countryCode?->short_name ?? 'CA';

                return Arr::except($addressData, 'id');
            }

            ShippingAddress::query()
                ->where('store_id', $store->id)
                ->delete();

            $shipping = new ShippingAddress;
            $shipping->fill($addressData);
            $shipping->save();

            $addressId = $this->generateEasyPostAddress($addressData);

            if ($addressId == -1) {
                throw new Exception('Unable to verify the address', 400);
            }

            $shipping->easypost_id = $addressId;
            $shipping->save();

            return ShippingAddressResource::make($shipping)
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        } catch (InvalidRequestException $e) {
            $shipping = $shipping?->loadMissing('store');

            $to = config('app.env') === 'Production' ? 'supportdog@dogpackapp.com' : 'dogpackdevs@gmail.com';

            Mail::to($to)
                ->cc(['eric@dogpackapp.com', 'rehan@dogpackapp.com', 'dilip@dogpackapp.com', 'jonathan@dogpackapp.com'])
                ->send(new ShippingAddressInvalid($shipping));

            if (! $shipping) {
                return response()->json([], Response::HTTP_OK);
            }

            return ShippingAddressResource::make($shipping)
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'errors' => [
                    'keywords' => $e->getMessage(),
                    'code' => $e->getCode(),
                ],
            ], 400);
        }
    }

    private function generateEasyPostAddress($addressData): string|int
    {
        $store = resolve(Store::class);
        $store->load(['seller']);

        $address = $this->easyPostService->createAddress(
            [
                'street1' => $addressData['street1'],
                'city' => $addressData['city'],
                'zip' => $addressData['zipcode'],
                'state' => $addressData['province'],
                'country' => $addressData['country'],
                'name' => $store->seller->first_name ?? 'DogPack Customer',
                'email' => $store->seller->email ?? config('services.DOGPACK_EMAIL'),
            ]
        );

        return $address['id'] ?? -1;
    }

    public function getAddressComponent($components, $type): ?string
    {
        foreach ($components as $component) {
            if (in_array($type, $component['types'])) {
                return $component['long_name'];
            }
        }

        return null;
    }

    public function extractCity(array $addressComponents): ?string
    {
        foreach ($addressComponents as $component) {
            if (in_array('locality', $component['types'])) {
                return $component['long_name'];
            }
        }

        foreach ($addressComponents as $component) {
            if (in_array('postal_town', $component['types'])) {
                return $component['long_name'];
            }
        }

        foreach ($addressComponents as $component) {
            if (in_array('sublocality_level_1', $component['types'])) {
                return $component['long_name'];
            }
        }

        return null;
    }
}
