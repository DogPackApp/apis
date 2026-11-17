<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

        $cdn = config('services.marketplace_media');

        if ($cdn) {
            return rtrim((string) $cdn, '/').'/'.ltrim($path, '/');
        }

        return Storage::disk('public')->url($path);
    }

    public static function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }
}
