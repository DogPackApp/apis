<?php

namespace App\Jobs\Order;

use App\Mail\Order\OrderExportEmail;
use App\Models\Order\ExportOrders;
use App\Models\Order\StoreOrder;
use App\Models\Product\Item;
use App\Models\Store\Store;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ExportOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batchID;

    protected $filters;

    public $storeId;

    protected $store;

    protected $columnsToExport = [
        // Order Details
        'order_reference', // order number
        'order_created_at',
        'order_currency',
        'order_subtotal',
        'order_discount',
        'order_delivery_fee',
        'order_tax',
        'order_grand_total',
        'order_payment_method',
        'order_payment_status',
        'order_payment_date',
        'order_fulfillment_status',
        'order_status',
        'order_channel',
        // Customer & Shipping Details
        'customer_first_name',
        'customer_last_name',
        'customer_full_name',
        'customer_email',
        'customer_phone',
        'shipping_address_1',
        'shipping_address_2',
        'shipping_city',
        'shipping_state',
        'shipping_zipcode',
        'shipping_country',
        'shipping_method_selected_by_customer',
        'shipping_method_used_by_store',
        // Store details
        'store_name',
        'store_fulfillment_address_1',
        'store_fulfillment_address_2',
        'store_fulfillment_city',
        'store_fulfillment_state',
        'store_fulfillment_zipcode',
        'store_fulfillment_country',
        'store_return_address_1',
        'store_return_address_2',
        'store_return_city',
        'store_return_state',
        'store_return_zipcode',
        'store_return_country',
        'store_email',
        // "store_phone", not available in the store model for now
        // Product Details
        'product_sku',
        'product_name',
        'product_quantity',
        'product_unit_price',
        'product_unit_discount',
        'product_subtotal',
        'product_unit_weight',
        'product_total_weight',
        'product_weight_unit',
        'product_length',
        'product_width',
        'product_height',
        'product_dimension_unit',
        // Customs Data
        'customs_hs_code',
        'customs_country_of_origin',
        'customs_reason_for_export', // default: Sale of goods
        'customs_incoterm', // default: DAP (delivery at place)
        'customs_currency',
        'customs_duties_paid', // default: false for DAP
        'seller_discount_contribution',
        'dogpack_discount_contribution',
        'promo_code',
    ];

    protected $mapColumnsToExport = [
        'order_reference' => 'storeOrder.order_no',
        'order_created_at' => 'storeOrder.created_at',
        'product_sku' => 'product_snapshot.sku',
    ];

    public function __construct(array $data)
    {
        $this->batchID = $data['batch_id'];
        $this->filters = $data['filters'] ?? null;
        $this->storeId = $data['store_id'];
        $this->onQueue(config('queue.low'));
    }

    public function handle(): void
    {
        $exportOrderTask = null;
        try {
            $search = $this->filters['search'] ?? null;
            $fulfilmentStatus = $this->filters['fulfilment_status'] ?? null;
            $orderStatus = $this->filters['order_status'] ?? null;
            $paymentStatus = $this->filters['payment_status'] ?? null;
            $startDate = $this->filters['start_date'] ?? null;
            $endDate = $this->filters['end_date'] ?? null;

            $exportOrderTask = ExportOrders::where('batch_id', $this->batchID)
                ->where('store_id', $this->storeId)
                ->where('finished_at', null)
                ->first();

            if (! $exportOrderTask) {
                throw new \Exception('Export order task not found');
            }

            $store = Store::query()
                ->select('id', 'name', 'slug', 'seller_id', 'country_id')
                ->with('seller:id,email', 'address', 'country:id,currency_code')
                ->where('id', $this->storeId)
                ->first();

            if (! $store) {
                throw new \Exception('Store not found');
            }

            $this->store = $store;

            $orderQuery = StoreOrder::query()
                ->with('items', 'orderBilling', 'customer', 'manualFulfillment:store_order_id,carrier', 'easypost:store_order_id,rates')
                ->where('store_id', $this->storeId);

            if ($startDate && $endDate) {
                $startDate = Carbon::parse($startDate)->startOfDay();
                $endDate = Carbon::parse($endDate)->endOfDay();
                $orderQuery->whereBetween('created_at', [$startDate, $endDate]);
            }

            if ($search) {
                $orderQuery->where(function ($q) use ($search) {
                    $q->where('order_no', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($q2) use ($search) {
                            $q2->where('name', 'like', "%{$search}%");
                        });
                });
            }

            if ($fulfilmentStatus) {
                $orderQuery->where('fulfilment_status', $fulfilmentStatus);
            }

            if ($orderStatus) {
                $orderQuery->where('order_status', $orderStatus);
            }

            if ($paymentStatus) {
                $orderQuery->where('payment_status', $paymentStatus);
            }

            $orders = $orderQuery->get();

            // 1. Define file path
            $filename = 'orders-export-'.$this->batchID.'.csv';
            $path = "exports/csv/{$store->slug}/{$filename}";

            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, fields: $this->columnsToExport);

            $itemsMissingData = Item::query()
                ->select('id', 'category_id', 'origin')
                ->with('category:id,ht_code')
                ->whereIn('id',
                    collect($orders)
                        ->flatMap(fn ($order) => $order->items)
                        ->pluck('product_id')
                        ->values()
                        ->unique()
                        ->all()
                )
                ->get()
                ->keyBy('id');
            foreach ($orders as $order) {
                if (! ($order?->orderBilling && $order?->customer && $order?->items)) {
                    continue;
                }
                foreach ($order->items as $item) {
                    $missingData = $itemsMissingData[$item->product_id] ?? null;
                    fputcsv($handle, array_map(fn ($column) => $this->getRawData($order, $item, $column, $missingData), $this->columnsToExport));
                }
            }
            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            Log::info('Prepared CSV for store '.$store->name.' with batch ID '.$this->batchID);

            // 3. Save file to S3
            Storage::disk('mps3')->put($path, $content);

            // 4. Make it publicly visible
            Storage::disk('mps3')->setVisibility($path, 'public');

            Log::info('Saved CSV to S3 for store '.$store->name.' with batch ID '.$this->batchID);

            $exportOrderTask->file_path = $path;

            // 5. Get public URL
            $url = Storage::disk('mps3')->url($path);

            $url = config('services.marketplace_media').$path;

            Mail::to($store->seller?->email ?? config('services.DOGPACK_EMAIL'))->send(new OrderExportEmail([
                'store_name' => $store->name,
                'file_name' => $filename,
                'export_date' => $exportOrderTask->created_at,
                'filters' => $this->filters,
                'url' => $url,
                'orders_count' => $orders->count(),
                'items_count' => $orders->sum(fn ($order) => $order->__orderTotalQuantity()),
            ]));

            Log::info('Sent email to '.$store->seller->email.' for store '.$store->name.' with batch ID '.$this->batchID);

        } catch (\Exception $e) {
            if ($exportOrderTask) {
                $exportOrderTask->errors = [
                    'message' => $e->getMessage(),
                    'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 5),
                ];
            }
            Log::error('Export order job failed: '.$e->getMessage(), [
                'batch_id' => $this->batchID,
                'store_id' => $this->storeId,
                'trace' => $e->getTraceAsString(),
            ]);
        }
        try {
            if ($exportOrderTask) {
                $exportOrderTask->finished_at = Carbon::now();
                $exportOrderTask->save();
            }
        } catch (\Exception $e) {
            Log::error('Cannot save export order task: '.$e->getMessage(), [
                'batch_id' => $this->batchID,
                'store_id' => $this->storeId,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function getAmount(mixed $amount)
    {
        return ($amount ?? 0) / 100;
    }

    private function parseDate(mixed $date)
    {
        return $date ? Carbon::parse($date)->format('Y-m-d H:i:s') : '';
    }

    private function getRawData(mixed $order, mixed $item, string $column, mixed $missingData = null)
    {
        switch ($column) {
            case 'order_reference':
                return $order->order_no;
            case 'order_created_at':
                return $this->parseDate($order->created_at);
            case 'order_currency':
                return $this->store->country?->currency_code ?? 'USD';
            case 'order_subtotal':
                return $this->getAmount($order->orderBilling->subtotal) + $this->getAmount($order->orderBilling->discount); // since our subtotal is already discounted, we need to add the discount back to get the actual subtotal
            case 'order_discount':
                return $this->getAmount($order->orderBilling->discount);
            case 'order_delivery_fee':
                return data_get($order->shipping, 'origin.price', $this->getAmount($order->orderBilling->shipping_rate));
            case 'order_tax':
                return $this->getAmount($order->orderBilling->tax);
            case 'order_grand_total':
                return $this->getAmount($order->orderBilling->total);
            case 'order_payment_method':
                return 'card';
            case 'order_payment_status':
                return $order->payment_status ?? '';
            case 'order_payment_date':
                return $this->parseDate($order->created_at);
            case 'order_fulfillment_status':
                return $order->fulfilment_status ?? 'unfulfilled';
            case 'order_status':
                return $order->order_status ?? 'pending';
            case 'order_channel':
                return 'DogPack Marketplace';

                // Customer & Shipping Details
            case 'customer_first_name':
                $fname = explode(' ', $order->customer->name ?? '')[0] ?? '';

                return $fname;
            case 'customer_last_name':
                $lname = explode(' ', $order->customer->name ?? '')[1] ?? '';

                return $lname;
            case 'customer_full_name':
                return $order->customer->name ?? '';
            case 'customer_email':
                return $order->customer->email ?? '';
            case 'customer_phone':
                return $order->customer->phone ?? '';
            case 'shipping_address_1':
                return $order->customer->shipping_address['street1'] ?? '';
            case 'shipping_address_2':
                return $order->customer->shipping_address['street2'] ?? '';
            case 'shipping_city':
                return $order->customer->shipping_address['city'] ?? '';
            case 'shipping_state':
                return $order->customer->shipping_address['province'] ?? '';
            case 'shipping_zipcode':
                return $order->customer->shipping_address['zipcode'] ?? '';
            case 'shipping_country':
                return $order->customer->shipping_address['country'] ?? '';
            case 'shipping_method_selected_by_customer':
                if ($order->shipping_method === StoreOrder::DELIVERY_FLAT) {
                    return data_get($order->shipping, 'shipping_rate_name', '').' ( '.data_get($order->shipping, 'shipping_rate_description', '').' )';
                }
                if ($order->shipping_method === StoreOrder::DELIVERY_EASYPOST) {
                    return data_get($order->shipping, 'carrier', '').' ( '.data_get($order->shipping, 'service', '').' )';
                }

                return '';
            case 'shipping_method_used_by_store':
                if ($order->order_status === StoreOrder::ORDER_STATUS_MANUAL) {
                    return $order->manualFulfillment?->carrier ?? 'manual';
                }

                return $order->easypost?->rates ? data_get($order->easypost->rates, 'carrier', '').' ( '.data_get($order->easypost->rates, 'service', '').' )' : '';

                // Store details
            case 'store_name':
                return $this->store->name ?? '';
            case 'store_fulfillment_address_1':
                return $this->store->address?->street1 ?? '';
            case 'store_fulfillment_address_2':
                return $this->store->address?->street2 ?? '';
            case 'store_fulfillment_city':
                return $this->store->address?->city ?? '';
            case 'store_fulfillment_state':
                return $this->store->address?->province ?? '';
            case 'store_fulfillment_zipcode':
                return $this->store->address?->zipcode ?? '';
            case 'store_fulfillment_country':
                return $this->store->address?->country ?? '';
            case 'store_return_address_1':
                return $this->store->address?->street1 ?? '';
            case 'store_return_address_2':
                return $this->store->address?->street2 ?? '';
            case 'store_return_city':
                return $this->store->address?->city ?? '';
            case 'store_return_state':
                return $this->store->address?->province ?? '';
            case 'store_return_zipcode':
                return $this->store->address?->zipcode ?? '';
            case 'store_return_country':
                return $this->store->address?->country ?? '';
            case 'store_email':
                return $this->store->seller?->email ?? '';
            case 'store_phone':
                return $this->store->address?->phone ?? '';

                // Product Details
            case 'product_sku':
                return $item->product_snapshot['sku'] ?? '';
            case 'product_name':
                return $item->product_snapshot['name'] ?? '';
            case 'product_quantity':
                return $item->quantity ?? 1;
            case 'product_unit_price':
                return $this->getAmount($item->original_price);
            case 'product_unit_discount':
                return $this->getAmount($item->original_price) - $this->getAmount($item->subtotal) / ($item->quantity ?? 1);
            case 'product_subtotal':
                return $this->getAmount($item->subtotal);
            case 'product_unit_weight':
                return $item->product_snapshot['weight']['single'] ?? 0;
            case 'product_total_weight':
                return ($item->product_snapshot['weight']['single'] ?? 0) * ($item->quantity ?? 0);
            case 'product_weight_unit':
                return $item->product_snapshot['weight']['unit'] ?? '';

                // Customs Data
            case 'customs_hs_code':
                $ht_code = $item->product_snapshot['ht_code'] ?? $missingData?->category?->ht_code ?? '';

                return $ht_code;
            case 'customs_country_of_origin':
                return $item->product_snapshot['origin'] ?? $missingData?->origin ?? '';
            case 'customs_reason_for_export':
                return 'Sale of goods';
            case 'customs_incoterm':
                return 'DAP';
            case 'customs_currency':
                return $this->store->country?->currency_code ?? 'USD';
            case 'customs_duties_paid':
                return 'no'; // default: false for DAP
            case 'seller_discount_contribution':
                return $this->getAmount($order->orderBilling->seller_contribution);
            case 'dogpack_discount_contribution':
                return $this->getAmount($order->orderBilling->dp_contribution);
            case 'promo_code':
                return $order->orderBilling->promo['code'] ?? '';
            default:
                return '';
        }
    }
}
