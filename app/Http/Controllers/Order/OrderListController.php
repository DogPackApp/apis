<?php

namespace App\Http\Controllers\Order;

use App\Http\Resources\Order\OrderResource;
use App\Models\Order\StoreOrder;
use App\Models\Store\Store;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class OrderListController
{
    public function __invoke(Request $request): JsonResponse|AnonymousResourceCollection
    {
        try {
            $validator = Validator::make($request->all(), [
                'search' => ['nullable', 'string', 'max:255'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'fulfilment_status' => ['nullable', Rule::in(StoreOrder::$fulfilmentStatus)],
                'order_status' => ['nullable', Rule::in(StoreOrder::$orderStatus)],
                'payment_status' => ['nullable', Rule::in(StoreOrder::$paymentStatus)],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date', 'after_or_equal:start_date', 'required_with:start_date'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $orders = StoreOrder::query()
                ->where('store_id', resolve(Store::class)->id)
                ->with([
                    'customer:id,name,store_order_id',
                    'orderBilling',
                ])
                ->withSum('items', 'quantity')
                ->when($request->filled('fulfilment_status'), function ($query) use ($request) {
                    $fulfilmentStatus = $request->input('fulfilment_status');
                    $query->where('fulfilment_status', $fulfilmentStatus);
                })
                ->when($request->filled('order_status'), function ($query) use ($request) {
                    $orderStatus = $request->input('order_status');
                    $query->where('order_status', $orderStatus);
                })
                ->when($request->filled('payment_status'), function ($query) use ($request) {
                    $paymentStatus = $request->input('payment_status');
                    $query->where('payment_status', $paymentStatus);
                })
                ->when($request->filled('search'), function ($query) use ($request) {
                    $search = $request->input('search');

                    $query->where(function ($q) use ($search) {
                        // If the search starts with #, then it is an order number
                        $q->when(Str::startsWith($search, '#'), function ($q) use ($search) {
                            $q->where('order_no', 'like', '%'.Str::after($search, '#').'%');
                        }, function ($q) use ($search) {
                            $q->where('order_no', 'like', "%{$search}%")
                                ->orWhereHas('customer', function ($q2) use ($search) {
                                    $q2->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                });
                        });
                    });
                })
                ->when($request->filled('start_date'), function ($query) use ($request) {
                    $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
                    $endDate = Carbon::parse($request->input('end_date'))->endOfDay();

                    $query->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($validator->validated()['per_page'] ?? 10);

            return OrderResource::collection($orders);
        } catch (Exception $e) {
            if (app()->environment() !== 'production') {
                $message = $e->getMessage();
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
