<?php

namespace App\Services\Store;

use EasyPost\Address;
use EasyPost\EasyPostClient;

class EasyPostService
{
    private EasyPostClient $client;

    public function __construct()
    {
        $this->client = new EasyPostClient(config('services.marketplace_easypost_api'));
    }

    /**
     * @param  array<string, mixed>  $address
     */
    public function createAddress(array $address): Address
    {
        return $this->client->address->create([
            'zip' => $address['zip'],
            'country' => $address['country'] ?? 'CA',
            'street1' => $address['street1'],
            'street2' => $address['street2'] ?? null,
            'city' => $address['city'],
            'state' => $address['state'] ?? 'QC',
            'name' => $address['name'] ?? 'DogPack App',
            'email' => $address['email'] ?? config('services.DOGPACK_EMAIL'),
            'phone' => $address['phone'] ?? '514-570-2183',
        ]);
    }
}
