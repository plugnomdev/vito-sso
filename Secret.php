<?php

namespace App\Vito\Plugins\Plugnomdev\VitoSso;

use Illuminate\Support\Facades\File;

/**
 * The secret shared with Plugnom's API, which signs the sign-in links.
 *
 * It lives in a file rather than .env: Vito caches its config in
 * production, so env() returns null at runtime outside config files.
 */
class Secret
{
    public static function path(): string
    {
        return storage_path('app/plugnom-sso/secret');
    }

    public static function get(): ?string
    {
        $path = self::path();
        $secret = File::exists($path) ? trim((string) File::get($path)) : '';

        return strlen($secret) >= 32 ? $secret : null;
    }

    public static function ensure(): string
    {
        if ($secret = self::get()) {
            return $secret;
        }

        $secret = bin2hex(random_bytes(32));
        File::ensureDirectoryExists(dirname(self::path()), 0700);
        File::put(self::path(), $secret);
        @chmod(self::path(), 0600);

        return $secret;
    }
}
