<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Models\Store\Store;
use App\Services\StripeKeyService;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;

class StripeCalculateNextBillingController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request)
    {
        try {
            $store = Store::with('storesubscription')->where('id', auth()->user()->store->id)->first();

            if (! $store->storesubscription) {
                return $this->errorResponse(['Store does not have an active subscription.'], Response::HTTP_NOT_FOUND);
            }

            $stripe = new StripeClient(StripeKeyService::key($store->country_id));

            $upcomingInvoice = $stripe->invoices->upcoming([
                'customer' => $store->stripe_customer_id,
                'subscription' => $store->storesubscription->stripe_subscription_id,
            ]);

            // Calculate the next deductible amount with tax (total includes the tax)
            $total_due_with_tax = $upcomingInvoice->total; // This includes both base price and tax

            return $this->successResponseWithData([
                'next_deductible_amount_with_tax' => $total_due_with_tax / 100,
                'currency' => $upcomingInvoice->currency,
                'due_date' => Carbon::createFromTimestamp($upcomingInvoice->next_payment_attempt),
            ]);
        } catch (ApiErrorException $e) {
            DB::rollBack();

            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(app()->isProduction() ? 'Something went wrong.' : $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
