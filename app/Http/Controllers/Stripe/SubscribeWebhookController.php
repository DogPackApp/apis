<?php

namespace App\Http\Controllers\Stripe;

use App\Http\Controllers\Controller;
use App\Mail\Stripe\PaymentFailedSupportMail;
use App\Models\Stripe\StorePayment;
use App\Models\Stripe\StoreSubscription;
use App\Services\OnboardingService;
use App\Services\Order\Integrations\AvalaraService;
use App\Services\StripeKeyService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;

class SubscribeWebhookController extends Controller
{
    protected $stripe;

    public function __invoke(Request $request)
    {
        $payload = $request->all();
        $eventType = $payload['type'] ?? null;

        if (! $eventType) {
            return response()->json(['error' => 'Invalid event'], 400);
        }

        $data = $payload['data']['object'];

        if ($eventType === 'invoice.payment_succeeded') {
            $this->handlePaymentSucceeded($data);
        }

        if ($eventType === 'invoice.payment_failed') {
            $this->handlePaymentFailed($data);
        }

        if ($eventType === 'customer.subscription.deleted') {
            $this->handleSubscriptionDeleted($data);
        }

        return response()->json(['received' => true]);
    }

    public function handlePaymentSucceeded($invoice)
    {
        DB::beginTransaction();
        try {
            $subscriptionId = $invoice['subscription']
                ?? $invoice['parent']['subscription_details']['subscription']
                ?? null;

            if (! $subscriptionId) {
                return;
            }

            $storeSub = StoreSubscription::with('store.seller')->where('stripe_subscription_id', $subscriptionId)
                ->orderBy('id', 'desc')
                ->first();

            if (! $storeSub) {
                return;
            }

            $this->stripe = new StripeClient(StripeKeyService::key($storeSub->store->country_id));

            $existingPayment = StorePayment::where('stripe_invoice_id', $invoice['id'])->first();
            if ($existingPayment?->status === StorePayment::STATUS_PAID) {
                Log::info('Payment already processed for invoice', ['invoice_id' => $invoice['id']]);
                DB::commit();

                return;
            }

            $paidAt = null;

            if (! $paidAt && ! empty($invoice['status_transitions']['paid_at'])) {
                $paidAt = Carbon::createFromTimestamp($invoice['status_transitions']['paid_at']);
            }

            (new OnboardingService)->completeSubscription($storeSub->store->seller);

            $storepayment = null;
            if ($existingPayment) {
                $storepayment = $existingPayment;
            } else {
                $storepayment = new StorePayment;
                $storepayment->store_id = $storeSub->store_id;
                $storepayment->stripe_invoice_id = $invoice['id'];
                $storepayment->failure_message = null;
            }
            $storepayment->failure_message = null;
            $storepayment->amount = $invoice['amount_paid'] / 100;
            $storepayment->currency = $invoice['currency'];
            $storepayment->status = StorePayment::STATUS_PAID;
            $storepayment->billing_reason = $invoice['billing_reason'] ?? null;
            $storepayment->receipt_url = $invoice['invoice_pdf'] ?? null;
            $storepayment->paid_at = $paidAt;
            $storepayment->save();

            $taxAmount = ($invoice['tax'] ?? 0) / 100;
            $isUkStore = (int) $storeSub->store->country_id === 230;
            if (! $isUkStore && $taxAmount > 0) {
                try {
                    $customerAddress = [];
                    if (! empty($storeSub->store->stripe_customer_id)) {
                        try {
                            $stripeCustomer = $this->stripe->customers->retrieve($storeSub->store->stripe_customer_id);
                            if (isset($stripeCustomer->address)) {
                                $customerAddress = [
                                    'line1' => $stripeCustomer->address->line1 ?? '',
                                    'line2' => $stripeCustomer->address->line2 ?? '',
                                    'city' => $stripeCustomer->address->city ?? '',
                                    'state' => $stripeCustomer->address->state ?? '',
                                    'postal_code' => $stripeCustomer->address->postal_code ?? '',
                                    'country' => $stripeCustomer->address->country ?? '',
                                ];
                            }
                        } catch (Exception $e) {
                            Log::warning('Failed to retrieve Stripe customer address for Avalara', [
                                'customer_id' => $storeSub->store->stripe_customer_id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    $customerCode = $storeSub->store->uuid ?? 'STORE-'.$storeSub->store_id;

                    $avalaraService = new AvalaraService;
                    $avalaraResult = $avalaraService->postStripeInvoiceToAvalara(
                        $invoice,
                        $customerAddress,
                        $customerCode,
                        3
                    );

                    if ($avalaraResult && isset($avalaraResult['code'])) {
                        $storepayment->avalara_transaction_code = $avalaraResult['code'];
                        $storepayment->save();
                    }
                } catch (Exception $e) {
                    Log::error('Failed to post invoice to Avalara', [
                        'invoice_id' => $invoice['id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                Log::info('Skipping Avalara registration - tax amount is zero', [
                    'invoice_id' => $invoice['id'] ?? null,
                    'tax_amount' => $taxAmount,
                ]);
            }

            $newPlanLine = collect($invoice['lines']['data'])->firstWhere('type', 'subscription')
                ?? collect($invoice['lines']['data'])->first(fn ($line) => ($line['parent']['type'] ?? null) === 'subscription_item_details'
                );

            $storeSub->subscription_status = 'active';
            if ($newPlanLine) {
                $storeSub->current_period_start = Carbon::createFromTimestamp($newPlanLine['period']['start']);
                $storeSub->current_period_end = Carbon::createFromTimestamp($newPlanLine['period']['end']);
            }
            $storeSub->is_active = true;
            $storeSub->save();
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            if (app()->environment() !== 'production') {
                $message = $e;
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function handlePaymentFailed($invoice)
    {
        DB::beginTransaction();
        try {
            $subscriptionId = $invoice['subscription']
                ?? $invoice['parent']['subscription_details']['subscription']
                ?? null;

            if (! $subscriptionId) {
                return;
            }

            $storeSub = StoreSubscription::with('store.seller')->where('stripe_subscription_id', $subscriptionId)
                ->orderBy('id', 'desc')
                ->first();

            if (! $storeSub) {
                return;
            }

            $this->stripe = new StripeClient(StripeKeyService::key($storeSub->store->country_id));

            $existingPayment = StorePayment::where('stripe_invoice_id', $invoice['id'])->first();
            if ($existingPayment) {
                Log::info('Payment failure already processed for invoice', ['invoice_id' => $invoice['id']]);
                DB::commit();

                return;
            }

            $failureMessage = null;
            if (! empty($invoice['last_finalization_error']['message'])) {
                $failureMessage = $invoice['last_finalization_error']['message'];
            } elseif (! empty($invoice['payment_intent']['last_payment_error']['message'])) {
                $failureMessage = $invoice['payment_intent']['last_payment_error']['message'];
            } else {
                $paymentIntent = $this->stripe->paymentIntents->retrieve($invoice['payment_intent']);
                $failureMessage = $paymentIntent?->last_payment_error['code'] ?? null;
            }

            $storepayment = new StorePayment;
            $storepayment->store_id = $storeSub->store_id;
            $storepayment->stripe_invoice_id = $invoice['id'];
            $storepayment->amount = $invoice['amount_due'] / 100;
            $storepayment->currency = $invoice['currency'];
            $storepayment->status = StorePayment::STATUS_FAILED;
            $storepayment->paid_at = null;
            $storepayment->billing_reason = $invoice['billing_reason'] ?? null;
            $storepayment->receipt_url = null;
            $storepayment->failure_message = $failureMessage;
            $storepayment->save();

            try {
                Mail::send(new PaymentFailedSupportMail([
                    'entity_type' => 'store',
                    'reason' => 'Payment Failed for Store Subscription',
                    'entity_name' => $storeSub->store->name,
                    'entity_email' => $storeSub->store->seller->email,
                    'entity_uuid' => $storeSub->store->uuid,
                    'failure_message' => $failureMessage,
                ]));
            } catch (Exception $e) {
                Log::error('Failed to send payment failed support email: '.$e->getMessage());
            }

            $storeSub->subscription_status = 'past_due';
            $storeSub->is_active = false;
            $storeSub->save();
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            if (app()->environment() !== 'production') {
                $message = $e;
            } else {
                $message = 'Something went wrong';
            }

            return response()->json(['errors' => ['message' => $message]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function handleSubscriptionDeleted($data)
    {
        $subscriptionId = $data['id'];
        $storeSub = StoreSubscription::where('stripe_subscription_id', $subscriptionId)
            ->orderBy('id', 'desc')
            ->first();

        if ($storeSub) {
            $storeSub->update([
                'subscription_status' => 'cancelled',
                'is_active' => false,
            ]);
        }
    }
}
