<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Models\Store\Store;
use App\Services\StripeKeyService;
use DB;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;
use Validator;

class StripeAddCardController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request)
    {
        DB::beginTransaction(); // Start transaction
        try {
            $validator = Validator::make($request->all(), [
                'payment_method' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $store = Store::with('storesubscription')->where('id', auth()->user()->store->id)->first();
            $stripe = new StripeClient(StripeKeyService::key($store->country_id));

            // Ensure the store has a Stripe customer ID
            if (! $store->stripe_customer_id) {
                return $this->errorResponse('You haven\'t subscribe to any plan yet!', Response::HTTP_BAD_REQUEST);
            }
            // Attach the new payment method to the customer
            $stripe->paymentMethods->attach($request->payment_method, [
                'customer' => $store->stripe_customer_id,
            ]);

            DB::commit();

            return $this->successResponse('New payment method added successfully.');

        } catch (ApiErrorException $e) {
            DB::rollBack();

            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(app()->isProduction() ? 'Something went wrong.' : $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
