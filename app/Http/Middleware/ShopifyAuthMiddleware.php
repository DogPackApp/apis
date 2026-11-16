<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ShopifyAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::channel('shopify')->info('PRODUCT PAYLOAD RECEIVED FROM SHOPIFY FOR INITIAL SYNC', [
            'payload' => $request->all(),
            'headers' => $request->header(),
        ]);

        $bearer = explode(' ', (string) $request->header('X-Shopify-DP-Token'))[1] ?? null;

        if ($bearer !== config('services.shopify_hmac_token')) {
            return response()->json('Unauthorized.', Response::HTTP_UNAUTHORIZED);
        }

        $storeId = $request->header('X-Shopify-DP-Store');
        if (empty($storeId) || ! Str::isUuid($storeId)) {
            return response()->json('Unauthorized.', Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
