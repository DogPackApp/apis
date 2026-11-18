<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Throwable;

class CacheService
{
    public function forgetAllByPattern(string $pattern): bool
    {
        try {
            $redis = Cache::getRedis();
            $prefix = config('cache.prefix') ? config('cache.prefix').':' : '';
            $keys = $redis->keys($pattern);
            if (! empty($keys)) {
                foreach ($keys as $fullKey) {
                    $key = $prefix ? str_replace($prefix, '', $fullKey) : $fullKey;
                    Cache::forget($key);
                }
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
