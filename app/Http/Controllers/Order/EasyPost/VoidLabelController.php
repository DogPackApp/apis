<?php

namespace App\Http\Controllers\Order\EasyPost;

use App\Exceptions\EasyPostPreTransitException;
use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\Order\OrderResource;
use App\Mail\Order\OrderShippingLabelVoidedMail;
use App\Models\Order\Concerns\UpdateOrderStatus;
use App\Models\Order\EasyPost;
use App\Models\Order\Timeline;
use App\Services\Order\EasyPostService;
use App\Services\Order\PayoutService;
use App\Services\Order\TimelineService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class VoidLabelController extends Controller
{
    use RespondsWithSellerJson;
    use UpdateOrderStatus;

    public EasyPostService $easyPostService;

    public function __construct()
    {
        $this->easyPostService = new EasyPostService;
    }

    /**
     * @throws Exception
     */
    public function __invoke(EasyPost $easyPosts): EasyPost|JsonResponse|OrderResource
    {
        try {
            DB::beginTransaction();

            $voidedEasyPosts = $this->easyPostService->void($easyPosts, true);

            $easyPosts->load(['order']);
            $this->markAsUnfulfilled($easyPosts->order)->save();

            (new PayoutService($easyPosts->order->store_id))->remove($easyPosts->order);

            (new TimelineService)->for(Timeline::FULFILLMENT_VOIDED, $easyPosts->order->id)->generate();

            Mail::to($easyPosts->order->store->seller->email ?? config('mail.address.from'))->send(new OrderShippingLabelVoidedMail(auth()->user(), $easyPosts->order->order_no));

            DB::commit();

            return new OrderResource($easyPosts->order);

        } catch (EasyPostPreTransitException $e) {
            return $this->errorResponse($e->getMessage());
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
