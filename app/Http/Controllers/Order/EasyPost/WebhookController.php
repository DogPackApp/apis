<?php

namespace App\Http\Controllers\Order\EasyPost;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Jobs\Order\EasyPostLogJob;
use App\Mail\Order\OrderAskReviewMail;
use App\Mail\Order\OrderShippingLabelScannedMail;
use App\Models\Order\Concerns\UpdateOrderStatus;
use App\Models\Order\EasyPost;
use App\Models\Order\StoreOrder;
use App\Models\Order\Timeline;
use App\Services\Order\EasyPostService;
use App\Services\Order\TimelineService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    use RespondsWithSellerJson;
    use UpdateOrderStatus;

    public EasyPostService $easyPostService;

    public array $lookFor = [
        'tracker.updated',
        'tracker.created',
    ];

    public function __construct()
    {
        $this->easyPostService = new EasyPostService;
    }

    /**
     * @throws Exception
     */
    public function __invoke(Request $request)
    {
        try {

            $easyPostRequest = $request->all();

            EasyPostLogJob::dispatchSync([
                'data' => $easyPostRequest,
            ]);

            DB::beginTransaction();

            if (! isset($easyPostRequest['result']['id'])) {
                Log::info('[EASYPOST WEBHOOK]: No Tracker ID Found, skipping...', $request->all());
            }

            // tracker.updated, tracker.created
            if (! in_array($easyPostRequest['description'], $this->lookFor)) {
                Log::info('[EASYPOST WEBHOOK]: No Looking for these updates, skipping...', $request->all());
            }

            $trackerId = $easyPostRequest['result']['id'];

            $easyPost = EasyPost::query()
                ->with('order.customer')
                ->where('trk_id', $trackerId)
                ->first();

            if (empty($easyPost?->order?->customer)) {
                Log::info("[EASYPOST WEBHOOK]: Tracker ID [$trackerId] not found in our database, skipping...", $request->all());

                return response()->json([], Response::HTTP_OK);
            }

            $easyPost->status = $easyPostRequest['result']['status'] ?? 'unknown';

            switch ($easyPost->status) {
                case StoreOrder::ORDER_STATUS_DELIVERED:
                    $this->markOrderStatus($easyPost->order, StoreOrder::ORDER_STATUS_DELIVERED)->save();
                    // $delay = Carbon::now()->addMinutes(2);
                    $delay = Carbon::now()->addDays(2);
                    Mail::to($easyPost->order->customer->email ?? config('mail.address.from'))
                        ->later($delay, new OrderAskReviewMail($easyPost->order->customer, $easyPost->order->uuid));
                    (new TimelineService)->for(Timeline::ORDER_EMAIL_REVIEW, $easyPost->order->id)->generate();
                    break;
                case 'in_transit':
                    if ($easyPost->order->order_status === StoreOrder::ORDER_STATUS_SHIPPED) {
                        break; // skip if the order is already shipped from origin facility
                    }
                    Mail::to($easyPost->order->customer->email ?? config('mail.address.from'))
                        ->send(new OrderShippingLabelScannedMail($easyPost->order->customer, $easyPost->order));
                    $this->markOrderStatus($easyPost->order, StoreOrder::ORDER_STATUS_SHIPPED)->save();
                    break;
                case 'out_for_delivery':
                    $this->markOrderStatus($easyPost->order, StoreOrder::ORDER_STATUS_OUT_FOR_DELIVERY)->save();
                    break;
                case StoreOrder::ORDER_STATUS_CANCELLED:
                    $this->markOrderStatus($easyPost->order, StoreOrder::ORDER_STATUS_CANCELLED)->save();
                    break;
                default:
                    break;
            }

            $easyPost->save();

            DB::commit();

            return response()->json([], Response::HTTP_OK);

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
