<?php

namespace App\Services\Store;

use Illuminate\Support\Facades\Http;

class EasyPostService
{
    /**
     * @param  array<string, mixed>  $address
     * @return array<string, mixed>
     */
    public function createAddress(array $address): array
    {
        $response = Http::withBasicAuth((string) config('services.marketplace_easypost_api'), '')
            ->post('https://api.easypost.com/v2/addresses', [
                'address' => [
                    'zip' => $address['zip'],
                    'country' => $address['country'] ?? 'CA',
                    'street1' => $address['street1'],
                    'street2' => $address['street2'] ?? null,
                    'city' => $address['city'],
                    'state' => $address['state'] ?? 'QC',
                    'name' => $address['name'] ?? 'DogPack App',
                    'email' => $address['email'] ?? config('services.DOGPACK_EMAIL'),
                    'phone' => $address['phone'] ?? '514-570-2183',
                ],
            ]);

        return $response->json() ?? [];
    }
}
