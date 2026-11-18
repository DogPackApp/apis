<?php

namespace App\Services\Order;

use App\Exceptions\EasyPostPreTransitException;
use App\Models\Order\EasyPost;
use App\Services\Order\Cart\WeightConverter;
use EasyPost\Address;
use EasyPost\CustomsInfo;
use EasyPost\EasyPostClient;
use EasyPost\Exception\Api\ApiException;
use EasyPost\Parcel;
use EasyPost\Shipment;
use Illuminate\Support\Facades\Log;
use Throwable;

class EasyPostService
{
    private EasyPostClient $client;

    protected $googleService;

    public function __construct()
    {
        // Initialize the EasyPost client with your API key
        $this->googleService = new GoogleService;
        $this->client = new EasyPostClient(config('services.marketplace_easypost_api'));
    }

    public function updatePostalInfo(array $data)
    {

        if (! isset($data['postal_code'])) {
            return;
        }

        $newPostalCode = $data['postal_code'];
        $sessionData = session('userAddressDetail', []);
        $oldPostalCode = $sessionData['postal_code'] ?? null;

        $postalChanged = $oldPostalCode !== $newPostalCode;

        // Always update postal code
        $sessionData['postal_code'] = $newPostalCode;

        // If postal changed → reset address & easy_post_id
        if ($postalChanged) {
            $sessionData['address'] = null;
            $sessionData['easy_post_id'] = null;
        }
        // If address is missing (either new or after reset), fetch from Google & EasyPost

        if (empty($sessionData['address'])) {
            $googleAddress = $this->googleService->getAddressBasedOnPIN($newPostalCode);
            if ($googleAddress) {
                $sessionData['address'] = [
                    'city' => $googleAddress['city'] ?? null,
                    'state' => $googleAddress['state'] ?? null,
                    'country' => $googleAddress['country'] ?? null,
                    'address' => $googleAddress['address'] ?? null,
                    'street1' => $googleAddress['street1'] ?? null,
                    'street2' => $googleAddress['street2'] ?? null,
                    'zipcode' => $googleAddress['zipcode'] ?? null,
                ];

                $easypostAddress = $this->createAddress([
                    'street1' => $googleAddress['street1'] ?? $googleAddress['address'],
                    'city' => $googleAddress['city'],
                    'zip' => $googleAddress['zipcode'],
                    'state' => $googleAddress['state'],
                    'country' => $googleAddress['country'],
                    'name' => $googleAddress['name'] ?? 'DogPack Customer',
                    'email' => $googleAddress['email'] ?? 'support@dogpackapp.com',
                ]);
                // echo '<pre>';
                // print_r($easypostAddress);
                // exit;
                if ($easypostAddress) {
                    $sessionData['easy_post_id'] = $easypostAddress['id'] ?? null;

                    session()->put('userAddressDetail', $sessionData);

                    return true;
                } else {
                    $arr = ['status' => 400, 'msg' => 'Postal Code invalid', 'result' => []];

                    return response()->json($arr);
                }
            } else {
                $arr = ['status' => 400, 'msg' => 'Postal Code invalid', 'result' => []];

                return response()->json($arr);
            }
        }

    }

    public function createAddress(array $address): Address
    {
        return $this->client->address->create([
            'zip' => $address['zip'],
            'country' => $address['country'] ?? 'CA',
            'street1' => $address['street1'],
            'street2' => $address['street2'] ?? null,
            'city' => $address['city'],
            'state' => $address['state'] ?? 'QC', // And a province,
            'name' => $address['name'] ?? 'DogPack App',
            'email' => $address['email'] ?? config('services.DOGPACK_EMAIL'),
            'phone' => $address['phone'] ?? '514-570-2183',
        ]);
    }

    public function retrieveAddress($easyPostId): Address
    {
        return $this->client->address->retrieve($easyPostId);
    }

    public function createSellerAddress($easyPostId): Address
    {
        return $this->client->address->retrieve($easyPostId);
    }

    public function logCheckoutItemWeights(?string $storeUuid, array $cartItems): void
    {
        $itemUnits = [1 => 'G', 2 => 'KG', 3 => 'MG', 4 => 'OZ', 5 => 'LB'];

        $this->log('EasyPost checkout item weights', [
            'store_uuid' => $storeUuid,
            'items' => collect($cartItems)->map(function ($cartItem) use ($itemUnits) {
                $unitId = $cartItem['weight']['unit'] ?? null;
                $ounces = (float) ($cartItem['weightinkg'] ?? 0);
                $parcelOunces = ! empty($cartItem['hasVariants']) ? $ounces * 100 : $ounces;
                $quantity = (int) ($cartItem['quantity'] ?? 0);

                return [
                    'title' => $cartItem['title'] ?? null,
                    'item_uuid' => $cartItem['item_uuid'] ?? null,
                    'sku' => $cartItem['sku'] ?? null,
                    'quantity' => $quantity,
                    'has_variants' => (bool) ($cartItem['hasVariants'] ?? false),
                    'before' => [
                        'value' => $cartItem['weight']['value'] ?? null,
                        'unit' => $itemUnits[$unitId] ?? $unitId,
                    ],
                    'after_ounces' => $ounces,
                    'used_in_parcel_ounces' => $parcelOunces,
                    'line_ounces' => $quantity * $parcelOunces,
                ];
            })->values()->all(),
        ]);
    }

    public function logParcelRequest(?string $storeUuid, array $parcel): void
    {
        $this->log('EasyPost parcel request', [
            'store_uuid' => $storeUuid,
            'parcel' => $parcel,
        ]);
    }

    public function logParcelResponse(?string $storeUuid, Parcel $parcel): void
    {
        $this->log('EasyPost parcel response', [
            'store_uuid' => $storeUuid,
            'parcel' => json_decode((string) $parcel, true),
        ]);
    }

    public function logShipmentRates(?string $storeUuid, Shipment $shipment): void
    {
        $this->log('EasyPost shipment rates', [
            'store_uuid' => $storeUuid,
            'shipment_id' => $shipment->id ?? null,
            'rates' => array_map(
                fn ($rate) => json_decode((string) $rate, true),
                $shipment->rates ?? []
            ),
            'messages' => array_map(
                fn ($message) => json_decode((string) $message, true),
                $shipment->messages ?? []
            ),
        ]);
    }

    public function logCheckoutError(?string $storeUuid, Throwable $e): array
    {
        $error = ['message' => $e->getMessage()];

        if ($e instanceof ApiException) {
            try {
                $error['http_status'] = $e->getHttpStatus();
                $error['http_body'] = $e->getHttpBody();
            } catch (Throwable) {
            }
            $error['code'] = $e->code;
            $error['errors'] = $e->errors;
        }

        $this->log('EasyPost checkout error', [
            'store_uuid' => $storeUuid,
            'error' => $error,
        ]);

        return $error;
    }

    private function log(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }

    public function createParcel(array $parcel): Parcel
    {
        return $this->client->parcel->create([
            'length' => $parcel['length'],
            'width' => $parcel['width'],
            'height' => $parcel['height'],
            'weight' => $parcel['weight'],
        ]);
    }

    public function verifyAddress($addressId): bool
    {
        $verification = $this->client->address->verify($addressId);

        $isDeliverable = json_decode($verification, true)['verifications']['delivery']['success'];

        if (! $isDeliverable) {
            return false;
        }

        return true;
    }

    public function createShipment(array $shipment, ?CustomsInfo $customs = null): Shipment
    {
        $shipmentData = [
            'from_address' => $shipment['from'],
            'to_address' => $shipment['to'],
            'parcel' => $shipment['parcel'],
            'options' => [
                'label_format' => 'PNG',
                'label_size' => '4x6',
            ],
        ];

        if ($customs !== null) {
            $shipmentData['customs_info'] = $customs;
        }

        return $this->client->shipment->create($shipmentData);
    }

    public function createCustomsInfo($store, $items, ?string $originCountry = null)
    {
        if ($originCountry === null && ! empty($items[0]['origin_country'])) {
            $originCountry = $items[0]['origin_country'];
        }

        $originCountry = strtoupper((string) ($originCountry ?? 'US'));

        $payload = [
            'customs_certify' => true,
            'customs_signer' => $store,
            'contents_type' => 'merchandise',
            'contents_explanation' => 'Pet Products',
            'restriction_type' => 'none',
            'customs_items' => $items,
        ];

        // NOEEI is a US export exemption — only valid for US-origin shipments
        if ($originCountry === 'US') {
            $payload['eel_pfc'] = 'NOEEI 30.37(a)';
        }

        return $this->client->customsInfo->create($payload);
    }

    /**
     * Build customs items from checkout cart items (same shape as RatesController::generateCustomInfoItems).
     */
    public function generateCustomInfoItemsFromCart(array $cartItems, string $originCountry = 'CA'): array
    {
        $customItems = [];
        $originCountry = strtoupper(strlen(trim($originCountry)) === 2 ? trim($originCountry) : 'CA');

        foreach ($cartItems as $cartItem) {
            if (($cartItem['is_digital'] ?? 0) == 1) {
                continue;
            }

            $weight = (float) ($cartItem['weightinkg'] ?? 0);
            if (isset($cartItem['hasVariants']) && $cartItem['hasVariants']) {
                $weight *= 100;
            }

            // Fallback when weightinkg is missing/zero (seen as 0.1 oz vs ~70 oz parcel)
            if ($weight <= 0 && isset($cartItem['weight']['value'])) {
                $weight = WeightConverter::convertWeight(
                    $cartItem['weight']['value'],
                    $cartItem['weight']['unit'] ?? 1,
                    4
                );
            }
            if ($weight <= 0) {
                $weight = 1;
            }

            $customItems[] = [
                'description' => $cartItem['title'] ?? 'Dog Accessories',
                'quantity' => (int) ($cartItem['quantity'] ?? 1),
                'weight' => $weight,
                'value' => round((float) ($cartItem['original']['discountedPrice'] ?? $cartItem['discountedPrice'] ?? 10.99), 2),
                'hs_tariff_number' => '42010000',
                'origin_country' => $originCountry,
                // Required by some international carriers (e.g. DhlEcs)
                'code' => (string) ($cartItem['sku'] ?? $cartItem['item_uuid'] ?? $cartItem['item_id'] ?? 'ITEM'),
            ];
        }

        return $customItems;
    }

    public function saveShipment($shipment, $order, $via = null): EasyPost
    {
        $easypost = new EasyPost;
        $easypost->store_order_id = $order->id;
        $easypost->shipment = $shipment;
        $easypost->via = $via ?? 'seller';
        $easypost->save();

        return $easypost;
    }

    public function buy($rate)
    {
        $shipment = $this->client->shipment->retrieve($rate['shipment_id']);

        json_decode($shipment, true);

        if (! isset($shipment->postage_label) && empty($shipment->postage_label)) {
            return $this->client->shipment->buy($shipment->id, $rate);
        }

        return $shipment;
    }

    public function retrieve($shipmentId)
    {
        return $this->client->shipment->retrieve($shipmentId);
    }

    public function void($easyPosts, $voidable = false)
    {
        // TODO: Currently, I am pulling the shipment via API and checking the tracker status
        // but, once I implement the Webhook, this should be removed as status will be
        // updated via the Webhook
        $shipment = json_decode(
            $this->retrieve($easyPosts?->rates['shipment_id']), true
        );

        if (empty($shipment)) {
            throw new EasyPostPreTransitException('Shipment not found');
        }

        if ($voidable && $shipment['tracker']['status'] !== EasyPost::PRE_TRANSIT && $shipment['tracker']['status'] !== EasyPost::UNKNOWN) {
            throw new EasyPostPreTransitException('Shipment already is in TRANSIT, cannot void the label');
        }

        if ($shipment['tracker']['status'] === EasyPost::PRE_TRANSIT || $shipment['tracker']['status'] === EasyPost::UNKNOWN) {
            $easyPosts->refund_status = EasyPost::VOIDED;
            $easyPosts->save();
        }

        if ($shipment['tracker']['status'] !== EasyPost::PRE_TRANSIT && $shipment['tracker']['status'] !== EasyPost::UNKNOWN) {
            $easyPosts->refund_status = EasyPost::CANCELLED;
            $easyPosts->save();
        }

        if ($easyPosts->rates['carrier'] === 'CanadaPost') {
            // TODO: trigger an email and mark this as voided, as CanadaPost, does not allow
            // refund via APIs.
            return $easyPosts;
        }

        if ($shipment['tracker']['status'] === EasyPost::PRE_TRANSIT) {
            $this->client->shipment->refund($easyPosts->rates['shipment_id']);
        }

        // TODO: trigger email + notification here too

        return true;
    }
}
