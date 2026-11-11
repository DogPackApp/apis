<?php

namespace App\Http\Controllers\Subscribe;

use App\Http\Controllers\Concerns\RespondsWithSellerJson;
use App\Http\Controllers\Controller;
use App\Models\Seller\OnboardingStatus;
use App\Models\Store\Store;
use App\Models\Stripe\StorePayment;
use App\Models\Stripe\StoreSubscription;
use Illuminate\Http\Request;

class StripeClearController extends Controller
{
    use RespondsWithSellerJson;

    public function __invoke(Request $request)
    {
        $store = Store::with('storesubscription')->where('id', $request->store_id)->first();

        $store->stripe_customer_id = null;
        $store->pm_type = null;
        $store->pm_last_four = null;
        $store->save();

        StoreSubscription::where('store_id', $store->id)->delete();
        StorePayment::where('store_id', $store->id)->delete();
        OnboardingStatus::where('seller_id', $store->seller->id)->update(['is_subscribe' => '0']);

    }
}
