<?php

namespace App\Http\Controllers\Order\EasyPost;

use App\Http\Controllers\Controller;
use App\Mail\Order\OrderShippingLabelCreatedMail;
use App\Models\Order\Concerns\OrderDetailsTrait;
use App\Models\Order\Concerns\UpdateOrderStatus;
use App\Models\Order\EasyPost;
use App\Models\Order\StoreOrder;
use App\Models\Order\Timeline;
use App\Models\Product\Item;
use App\Models\Store\Store;
use App\Services\Order\CurrencyService;
use App\Services\Order\EasyPostService;
use App\Services\Order\PayoutService;
use App\Services\Order\TimelineService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class BuyRateController extends Controller
{
    use OrderDetailsTrait, UpdateOrderStatus;

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
    public function __invoke(Request $request, StoreOrder $store_orders, string $rate): EasyPost|JsonResponse
    {
        try {

            $validator = Validator::make($request->all(), [
                'items' => ['nullable', 'array'],
                'is_digital' => ['sometimes', 'nullable', 'boolean'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $store = app(Store::class)->refresh()->load('country');

            DB::beginTransaction();

            $easypost = $store_orders->loadMissing(['easypost'])?->easypost;

            if (! $easypost) {
                return response()->json(['errors' => 'Rates not generated'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $selectedRate = null;
            foreach ($easypost->shipment['rates'] as $shipRates) {
                if ($shipRates['id'] === $rate) {
                    $selectedRate = $shipRates;
                }
            }

            if (! $selectedRate) {
                return response()->json(['errors' => 'Selected Rates not available'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $postage = $this->easyPostService->buy($selectedRate);

            // update the existing shipment details as tracker will
            // be added after buying the label
            $easypost->shipment = json_decode($postage, true);

            $easypost->rates = $selectedRate;
            $easypost->trk_id = $easypost->shipment['tracker']['id'] ?? 'na';

            $converted = $this->currencyService
                ->convert($postage['selected_rate']['rate'], 'USD', $store->country->currency_code);

            $easypost->subtotal = $converted['convertedPrice'] * 100;
            $easypost->total = $converted['convertedPrice'] * 100;
            $easypost->status = $postage['status'];

            $easypost->save();

            $easyposts = $store_orders->load('easyposts');
            $wasPreviousVoided = false;
            if (isset($easyposts->easyposts) && $easyposts->easyposts->count() > 0) {
                // to check if any of the previous shipping labels have been voided
                $wasPreviousVoided = $easyposts->easyposts->where('refund_status', EasyPost::VOIDED)->count() > 0;
            }
            $additionalData = json_encode([
                'shipment' => $easypost->shipment['tracker']['public_url'] ?? '#',
                'total' => $easypost->total / 100,
                'wasPreviousVoided' => $wasPreviousVoided,
            ]);

            // identify whether this order has
            // 1. Digital Items
            //   1a. Digital Items should be fulfilled
            // then we can mark the order as fulfilled
            // and add it to payouts.
            if ($this->hasDigitalItemFulfilled($store_orders)) {
                $this->markAsFulfilled($store_orders);
                $this->markItemAsFulfilled($store_orders, $validator->validated()['items']);
                $store_orders->save();

                (new TimelineService)->for(Timeline::FULFILLMENT_COMPLETED, $store_orders->id, $additionalData)->generate();

                (new PayoutService($store_orders->store_id))->add($store_orders);
            } else {
                $this->markAsPartiallyFulfilled($store_orders);
                $this->markItemAsFulfilled($store_orders, $validator->validated()['items']);
                $store_orders->save();
            }

            Mail::to($store_orders->customer->email ?? config('mail.address.from'))
                ->send(new OrderShippingLabelCreatedMail($store_orders->customer, $store_orders, $wasPreviousVoided));

            DB::commit();

            $easypost->subtotal = $easypost->subtotal / 100;
            $easypost->total = $easypost->total / 100;

            return $easypost;

        } catch (Exception $e) {
            DB::rollBack();
            if (app()->environment() !== 'production') {
                $message = $e;
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
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

        return round($weight * $conversionRates[$unit], 2);
    }

    public function cmToInches($cm): float
    {
        return $cm / 2.54;
    }
}
