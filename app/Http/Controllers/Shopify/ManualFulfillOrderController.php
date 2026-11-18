<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Http\Resources\Order\ManualFulfillmentResource;
use App\Models\Order\StoreOrder;
use App\Models\Store\StoreShopifyConnection;
use App\Services\Order\ManualFulfillService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ManualFulfillOrderController extends Controller
{
    public function __invoke(Request $request, ManualFulfillService $manualFulfillService): ManualFulfillmentResource|JsonResponse
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'shopify_order_id' => 'required|string',
                'tracking' => ['sometimes', 'nullable', 'string', 'max:5000'],
                'tracking_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
                'carrier' => ['sometimes', 'nullable', 'string', 'min:1', 'max:5000'],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $input = $validator->validated();

            $order = StoreOrder::with(['store'])
                ->where('source_id', $input['shopify_order_id'])
                ->firstOrFail();

            $response = $manualFulfillService->manualFulfill($order, [
                'tracking' => $input['tracking'] ?? null,
                'tracking_url' => $input['tracking_url'] ?? null,
                'carrier' => $input['carrier'] ?? null,
            ], 'shopify');

            DB::commit();

            if (StoreShopifyConnection::on('mysql_reader')
                ->where('store_id', $order->store_id)
                ->exists()) {
                Log::channel('shopify')->info('Shopify Dragon service executed this fulfillment.', [
                    'order_id' => $order->id,
                ]);
            }

            return $response;
        } catch (Exception $e) {
            DB::rollBack();

            $message = app()->environment() !== 'production'
                ? $e->getMessage()
                : 'Something went wrong';

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
