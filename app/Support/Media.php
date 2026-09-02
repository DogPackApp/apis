<?php

namespace App\Support;

class Media
{
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim((string) config('services.marketplace_media'), '/').'/'.ltrim($path, '/');
    }
}
