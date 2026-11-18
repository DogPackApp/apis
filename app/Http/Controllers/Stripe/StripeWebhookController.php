<?php

namespace App\Http\Controllers\Stripe;

use App\Http\Controllers\Controller;
use App\Mail\Stripe\StripeApprovedMail;
use App\Mail\Stripe\StripeMoreDetailsMail;
use App\Mail\Stripe\StripePendingMail;
use App\Models\Store\Store;
use App\Models\Stripe\StripeLog;
use App\Services\OnboardingService;
use App\Services\StripeKeyService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Stripe;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public $store;

    public function __invoke(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $sigHeader = $request->header('stripe-signature');
            $payload = $request->getContent();

            $verified = StripeKeyService::verifyWebhook($payload, $sigHeader, 'wb_secret');
            $event = $verified['event'];
            $config = $verified['config'];

            Stripe::setApiKey($config['key']);

            $store = Store::where('stripe_account_id', $event->data->object?->id ?? null)->first();

            StripeLog::insert([
                'store_id' => $store?->id ?? null,
                'description' => $event->type,
                'data' => json_encode($event),
                'created_at' => now(),
            ]);

            if (! $store) {
                Log::warning('PAYOUT::::::::::::: unknown stripe webhook');

                return response()->json(['status' => 'success']);
            }

            if ($event->type === 'account.updated') {
                $account = $event->data->object;
                $store->load('seller');

                if (
                    $account->details_submitted &&
                    (! empty($account->requirements->currently_due) || ! empty($account->requirements->past_due))
                ) {
                    $stripe = new StripeClient([
                        'api_key' => $config['key'],
                    ]);

                    $account_link = $stripe->accountLinks->create([
                        'account' => $store->stripe_account_id,
                        'return_url' => config('services.marketplace_fe_url').'/success',
                        'refresh_url' => config('services.marketplace_fe_url').'/payout',
                        'type' => 'account_onboarding',
                    ]);

                    if ($store->stripe_status !== Store::STRIPE_STATUS_MORE_DETAILS) {
                        $store->stripe_status = Store::STRIPE_STATUS_MORE_DETAILS;
                        $store->save();

                        Mail::to($store->seller->email ?? config('mail.address.from'))
                            ->send(new StripeMoreDetailsMail($store->name, $account_link->url));
                    }
                } elseif ($account->charges_enabled && $account->payouts_enabled) {
                    if ($store->stripe_status !== Store::STRIPE_STATUS_VERIFIED) {
                        $store->stripe_status = Store::STRIPE_STATUS_VERIFIED;
                        $store->save();

                        (new OnboardingService)->completeFinance($store->seller);

                        Mail::to($store->seller->email ?? config('mail.address.from'))
                            ->send(new StripeApprovedMail($store->name));
                    }
                } else {
                    if ($store->stripe_status !== Store::STRIPE_STATUS_PENDING) {
                        $store->stripe_status = Store::STRIPE_STATUS_PENDING;
                        $store->save();
                        Mail::to($store->seller->email ?? config('mail.address.from'))
                            ->queue(new StripePendingMail($store->name));
                    }
                }
            }

            DB::commit();

            return response()->json(['status' => 'success']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::info($e);

            return response()->json([
                'errors' => ['message' => app()->environment() !== 'production' ? $e->getMessage() : 'Something went wrong'],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
