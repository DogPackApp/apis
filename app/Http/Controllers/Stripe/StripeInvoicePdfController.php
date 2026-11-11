<?php

namespace App\Http\Controllers\Stripe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Models\Store\Store;
use App\Models\Stripe\StorePayment;
use App\Services\StripeKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;

class StripeInvoicePdfController extends Controller
{
    use RespondsWithSellerJson;

    /**
     * Retrieve a fresh PDF link for a Stripe invoice that belongs to the requested store.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'invoice_id' => ['required', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $store = resolve(Store::class);

            // Ensure the invoice belongs to the requested store
            $payment = StorePayment::query()
                ->where('store_id', $store->id)
                ->where('stripe_invoice_id', $validator->validated()['invoice_id'])
                ->first();

            if (! $payment) {
                return $this->errorResponse('Invoice not found', Response::HTTP_NOT_FOUND);
            }

            // Reuse the stored link while it still works
            if (! empty($payment->receipt_url) && $this->isLinkValid($payment->receipt_url)) {
                return $this->successResponseWithData([
                    'stripe_invoice_id' => $payment->stripe_invoice_id,
                    'invoice_pdf' => $payment->receipt_url,
                ]);
            }

            // Stored link is missing or expired: fetch a fresh one from Stripe and replace it
            $stripe = new StripeClient(StripeKeyService::key($store->country_id));
            $invoice = $stripe->invoices->retrieve($payment->stripe_invoice_id);

            if (empty($invoice->invoice_pdf)) {
                return $this->errorResponse('Invoice PDF is not available yet', Response::HTTP_NOT_FOUND);
            }

            $payment->update(['receipt_url' => $invoice->invoice_pdf]);

            return $this->successResponseWithData([
                'stripe_invoice_id' => $invoice->id,
                'invoice_pdf' => $invoice->invoice_pdf,
            ]);

        } catch (ApiErrorException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            return $this->errorResponse(app()->isProduction() ? 'Something went wrong.' : $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Expired Stripe links respond with a 4xx (e.g. 410 Gone). Any non-2xx
     * response or network failure is treated as expired.
     */
    private function isLinkValid(string $url): bool
    {
        try {
            return Http::timeout(5)->head($url)->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
