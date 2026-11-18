<?php

namespace App\Http\Controllers\Seller\Policy;

use App\Http\Controllers\Controller;
use App\Models\Product\Item;
use App\Models\Store\Policy;
use App\Models\Store\Store;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PolicyDeleteController extends Controller
{
    public function __invoke(Request $request, Policy $policy): JsonResponse
    {
        if ($policy->is_default) {
            return response()->json(['errors' => 'Default policy cannot be deleted'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();

        try {
            $defaultPolicy = Policy::query()
                ->where('store_id', resolve(Store::class)->id)
                ->where('is_default', true)
                ->first();

            if (! $defaultPolicy) {
                // [This is a rare case, add this as fail-safe]
                // if there is no default policy, the make the first policy as default
                // and move all the items existing in the current deleting policy
                // to the newly made default policy and delete the current policy
                $defaultPolicy = Policy::query()
                    ->where('store_id', resolve(Store::class)->id)
                    ->oldest()
                    ->first()->id;

                $defaultPolicy->update(['is_default' => 1]);
            }

            Item::query()
                ->where('policy_id', $policy->id)
                ->update(['policy_id' => $defaultPolicy->id]);

            $policy->delete();

            DB::commit();

            return response()->json(['message' => 'Policy deleted successfully'], Response::HTTP_OK);

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
}
