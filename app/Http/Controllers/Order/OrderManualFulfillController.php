<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\Order\ManualFulfillmentResource;
use App\Models\Order\Concerns\OrderDetailsTrait;
use App\Models\Order\Concerns\UpdateOrderStatus;
use App\Models\Order\StoreOrder;
use App\Models\Product\Item;
use App\Services\Order\ManualFulfillService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class OrderManualFulfillController extends Controller
{
    use OrderDetailsTrait, UpdateOrderStatus;
    use RespondsWithSellerJson;

    public function __invoke(Request $request, StoreOrder $order, ManualFulfillService $manualFulfillService): ManualFulfillmentResource|JsonResponse
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'tracking' => ['nullable', 'string', 'max:5000'],
                'carrier' => ['nullable', 'string', 'min:1', 'max:5000'],
                'items' => ['nullable', 'array'],
                'is_digital' => ['sometimes', 'nullable', 'boolean'],
            ]);
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $validated = $validator->validated();
            // old flow, where we don't care about the item is digital or not
            // this will also run when there is no digital product in the order
            if (! isset($validated['is_digital'])) {
                $manualFulfillService->manualFulfill($order, [
                    'tracking' => $request->input('tracking'),
                    'carrier' => $request->input('carrier'),
                ]);
                DB::commit();

                return $this->emptySuccessResponse();
            }

            // if the bunch of items is digital, then first check if the physical is fulfilled
            // else: just mark digital as fulfilled and rest leave as such
            if ($validated['is_digital'] == 1) {
                if ($this->areOtherItemFulfilled($order, $validator->validated()['items'], Item::TYPE_PHYSICAL)) {
                    $manualFulfillService->manualFulfillDigital($order, [
                        'tracking' => $request->input('tracking'),
                        'carrier' => $request->input('carrier'),
                    ]);
                    // order: fulfilled + item: fulfilled
                    $this->markAsFulfilled($order);
                    $this->markItemAsFulfilled($order, $validator->validated()['items']);
                } else {
                    // order: partial + item: fulfilled
                    $this->markItemAsFulfilled($order, $validator->validated()['items']);
                    $this->markAsPartiallyFulfilled($order);
                    $order->save();
                }
            }
            // if the bunch of items is physical, then first check if the digital is fulfilled
            // else: just mark physical as fulfilled and rest leave as such
            else {
                if ($this->areOtherItemFulfilled($order, $validator->validated()['items'], Item::TYPE_DIGITAL)) {
                    $manualFulfillService->manualFulfill($order, [
                        'tracking' => $request->input('tracking'),
                        'carrier' => $request->input('carrier'),
                    ]);
                    // order: fulfilled + item: fulfilled
                    $this->markAsFulfilled($order);
                    $this->markItemAsFulfilled($order, $validator->validated()['items']);
                } else {
                    // order: partial + item: fulfilled
                    $this->markItemAsFulfilled($order, $validator->validated()['items']);
                    $this->markAsPartiallyFulfilled($order);
                    $order->save();
                }
            }

            DB::commit();

            return $this->emptySuccessResponse();

        } catch (Exception $e) {
            DB::rollBack();

            if (app()->environment() !== 'production') {
                $message = $e->getMessage();
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
