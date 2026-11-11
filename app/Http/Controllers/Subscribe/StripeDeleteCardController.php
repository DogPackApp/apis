<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Services\StripeKeyService;
use Illuminate\Http\Request;
use Stripe\StripeClient;

class StripeDeleteCardController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request)
    {
        $request->validate(['payment_method' => 'required|string']);

        $store = auth()->user()->store;
        $stripe = new StripeClient(StripeKeyService::key($store->country_id));

        try {
            $stripe->paymentMethods->detach($request->payment_method);

            return response()->json(['success' => true, 'message' => 'Card deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
