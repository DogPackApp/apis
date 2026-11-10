<?php

namespace App\Http\Controllers\Order\EasyPost;

use App\Http\Controllers\Controller;
use App\Models\Order\Concerns\UpdateOrderStatus;
use App\Models\Order\EasyPost;
use App\Models\Order\StoreOrder;
use App\Models\Product\Item;
use App\Models\Store\Packaging;
use App\Services\Order\CurrencyService;
use App\Services\Order\EasyPostService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class RatesController extends Controller
{
    use UpdateOrderStatus;

    public EasyPostService $easyPostService;

    public CurrencyService $currencyService;

    public function __construct()
    {
        $this->easyPostService = new EasyPostService;
        $this->currencyService = new CurrencyService;
    }

    /**
     * @throws Exception
     */
    public function __invoke(Request $request, StoreOrder $store_orders): EasyPost|JsonResponse
    {
        try {

            $validator = Validator::make($request->all(), [
                'package' => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $order = $store_orders;
            $order->loadMissing(['easypost']);

            // TODO: Fix this, right now, every request will call EasyPost
            if ($order->easypost && ($order->easypost->created_at >= now()->subDays(1))) {
                // return $order->easypost;
            }

            $order->load('customer', 'store.address', 'items', 'store.defaultPackaging', 'store.country');

            $package = Packaging::query()
                ->where('uuid', $validator->validated()['package'] ?? null)
                ->first() ?? $order->store->defaultPackaging;

            $storeCurrency = $order->store?->country?->currency_code ?? 'CAD';

            // create Customer Address
            $customerAddress = $order->customer->shipping_address;
            $address = $this->easyPostService->createAddress(
                [
                    'street1' => $customerAddress['street1'],
                    'street2' => $customerAddress['street2'] ?? null,
                    'city' => $customerAddress['city'],
                    'zip' => $customerAddress['zipcode'],
                    'state' => $customerAddress['province'],
                    'country' => $customerAddress['country'],
                    'name' => $order->customer->name ?? 'DogPack Customer',
                    'email' => $order->customer?->email ?? 'support@dogpackapp.com',
                    'phone' => '514-570-2183',
                ]
            );

            $verification = $this->easyPostService->verifyAddress($address['id']);
            if (! $verification) {
                return response()->json('Address not found', 422);
            }

            // create Seller Address
            $sellerAddressModel = $order->store->address;

            $sellerAddress = $this->easyPostService->createAddress(
                [
                    'street1' => $sellerAddressModel->street1,
                    'street2' => $sellerAddressModel->street2 ?? null,
                    'city' => $sellerAddressModel->city,
                    'zip' => $sellerAddressModel->zipcode,
                    'state' => $sellerAddressModel->province,
                    'country' => $sellerAddressModel->country,
                    'name' => $order->store->name ?? 'DogPack Seller',
                    'email' => $order->store?->email ?? 'support@dogpackapp.com',
                    'phone' => '514-570-2183',
                ]
            );

            $verification = $this->easyPostService->verifyAddress($address['id']);
            if (! $verification) {
                return response()->json('Address not found', 422);
            }

            // Calculate the Order's Item Weights
            $products = $order->items;
            $weights = 0;
            foreach ($products as $product) {
                if ($product->quantity == $product->refunded) {
                    continue;
                }

                $weight = $product->product_snapshot['weight'];

                if (! empty($weight)) {
                    $calculateWeight = $weight['single'] ?? $weight['total'];

                    $totalWeight = $calculateWeight * ($product->quantity - $product->refunded);
                    $weights += $this->convertToOuncesProductWeight($totalWeight ?? 0, $weight['unit']);
                }
            }

            // Calculate the Packaging
            $parcel = [];
            $parcel['weight'] = $weights;

            // 1 => IN
            // 2 => CM
            if ($package->unit === 1) {
                $parcel['length'] = $package->length;
                $parcel['width'] = $package->width;
                $parcel['height'] = $package->height;
            } else {
                $parcel['length'] = $this->cmToInches($package->length);
                $parcel['width'] = $this->cmToInches($package->width);
                $parcel['height'] = $this->cmToInches($package->height);
            }

            // adding the empty weight of packaging to the whole parcel
            $parcel['weight'] += $this->convertToOuncesUnit($package->empty_weight,
                $package->empty_unit);

            $parcel['weight'] = round($parcel['weight'], 5);

            // parcel
            $parcel = $this->easyPostService->createParcel($parcel);
            // custom info => only if the there are two different country is involved

            if (($customerAddress['country'] ?? 'CA') === ($sellerAddress['country'] ?? 'CA')) {
                $customs = null;
            } else {
                $customs = $this->easyPostService->createCustomsInfo(
                    $order?->store?->name ?? 'DogPack App',
                    $this->generateCustomInfoItems($order->items)
                );
            }

            // create shipment
            $shipment = $this->easyPostService->createShipment([
                'parcel' => $parcel,
                'to' => $address,
                'from' => $sellerAddress,
            ], $customs);

            $shipment = json_decode($shipment, true);

            foreach ($shipment['rates'] as &$rates) {
                $converted = $this->currencyService->convert($rates['rate'], 'USD', $storeCurrency);

                $rates['store_rate'] = $rates['rate'];
                $rates['store_rate'] = 'USD';

                if (isset($converted['convertedPrice'])) {
                    $rates['store_rate'] = round($this->currencyService->convert($rates['rate'], 'USD', $storeCurrency)['convertedPrice'], 2);
                    $rates['exchange_rate'] = $this->currencyService->convert($rates['rate'], 'USD', $storeCurrency)['rate'];
                    $rates['store_currency'] = $storeCurrency;
                }
            }

            return $this->easyPostService->saveShipment($shipment, $order);

        } catch (Exception $e) {
            if (app()->environment() !== 'production') {
                $message = $e->getMessage();
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function convertToOuncesProductWeight(float $weight, string $unit): float
    {
        return match ($unit) {
            Item::WEIGHT_UNIT_G => $weight * 0.035274,
            Item::WEIGHT_UNIT_KG => $weight * 35.274,
            Item::WEIGHT_UNIT_MG => $weight * 0.000035274,
            Item::WEIGHT_UNIT_OZ => $weight,
            Item::WEIGHT_UNIT_LB => $weight * 16,
            default => $weight,
        };
    }

    public function convertToOuncesUnit(float $weight, int $unit): float
    {

        return match ($unit) {
            Packaging::PACKING_UNIT_LB => $weight * 16,
            Packaging::PACKING_UNIT_KG => $weight * 35.274,
            Packaging::PACKING_UNIT_OZ => $weight,
            Packaging::PACKING_UNIT_G => $weight * 0.035274,
            Packaging::PACKING_UNIT_MG => $weight * 0.000035274,
            default => $weight,
        };
    }

    public function convertToOunces($weight, $unit): float
    {
        $conversionRates = [
            Item::WEIGHT_UNIT_G => 0.03527396,
            Item::WEIGHT_UNIT_KG => 35.27396,
            Item::WEIGHT_UNIT_MG => 0.000035274,
            Item::WEIGHT_UNIT_LB => 16,
            Item::WEIGHT_UNIT_OZ => 1, // already in ounces
        ];

        if (! isset($conversionRates[$unit])) {
            throw new Exception("Unsupported weight unit: $unit");
        }

        return $weight * $conversionRates[$unit];
    }

    public function cmToInches($cm): float
    {
        return $cm / 2.54;
    }

    /**
     * @throws Exception
     */
    public function generateCustomInfoItems($items): array
    {
        $customItems = [];

        foreach ($items as $item) {

            $weight =
                $this->convertToOunces($item->product_snapshot['weight']['single'], $item->product_snapshot['weight']['unit']);

            $customItems[] = [
                'description' => $item->product_snapshot['name'] ?? 'Dog Accessories',
                'quantity' => $item->quantity ?? 1,
                'weight' => $weight ?? 50,
                'value' => ($item->price / 100) ?? 10.99,
                'hs_tariff_number' => $item->product_snapshot['ht_code'] ?? '42010000',
                'origin_country' => 'US',
            ];
        }

        return $customItems;
    }
}
