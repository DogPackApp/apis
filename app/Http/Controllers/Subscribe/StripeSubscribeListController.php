<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Http\Resources\Subscribe\PaymentResource;
use App\Models\Store\Store;
use App\Models\Stripe\StorePayment;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;
use Validator;

class StripeSubscribeListController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request)
    {

        try {
            $validator = Validator::make($request->all(), [
                'search' => ['nullable', 'string', 'max:255'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $orders = StorePayment::query()
                ->where('store_id', resolve(Store::class)->id)
                ->orderBy('created_at', 'desc')
                ->paginate($validator->validated()['per_page'] ?? 10);

            return PaymentResource::collection($orders);

        } catch (ApiErrorException $e) {

            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {

            return $this->errorResponse(app()->isProduction() ? 'Something went wrong.' : $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
