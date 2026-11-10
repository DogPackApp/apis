<?php

namespace App\Services\Order;

use Exception;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class GoogleService
{
    public function getAddressBasedOnPIN($zipCode): array
    {
        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $zipCode, // e.g., 'R0E0T0'
            'key' => config('services.marketplace_google_api'),
        ]);

        $responseData = $response->json();

        // Check if request was successful
        if (! $response->successful() || ! isset($responseData['status']) || $responseData['status'] !== 'OK') {
            throw new Exception($response->json()['error_message'] ?? 'Unknown Error', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $addressComponents = $responseData['results'][0]['address_components'];

        return [
            'country' => $this->getAddressComponent($addressComponents, 'country'),
            'state' => $this->getAddressComponent($addressComponents, 'administrative_area_level_1'),
            'city' => $this->getAddressComponent($addressComponents, 'locality'),
            'address' => $responseData['results'][0]['formatted_address'],
            'street1' => $this->getAddressComponent($addressComponents, 'sublocality_level_1'),
            'street2' => $this->getAddressComponent($addressComponents, 'sublocality_level_1'),
            'zipcode' => $this->getAddressComponent($addressComponents, 'postal_code'),
        ];
    }

    public function getAddressComponent($components, $type)
    {
        foreach ($components as $component) {
            if (in_array($type, $component['types'])) {
                return $component['long_name'];
            }
        }

        return null;
    }
}
