<?php

namespace App\Vito\Plugins\Plugnomdev\VitoSso;

use RuntimeException;

/**
 * base64url(json claims) . "." . base64url(HMAC-SHA256(secret, first part))
 *
 * Claims: email, exp (unix seconds), nonce, redirect (optional path).
 */
class SsoToken
{
    /** Longest a link may live, whatever exp the issuer chose. */
    public const MAX_TTL = 120;

    public static function make(string $secret, string $email, ?string $redirect = null, int $ttl = 60): string
    {
        $body = self::encode((string) json_encode(array_filter([
            'email' => $email,
            'exp' => time() + $ttl,
            'nonce' => bin2hex(random_bytes(16)),
            'redirect' => $redirect,
        ])));

        return $body.'.'.self::encode(hash_hmac('sha256', $body, $secret, true));
    }

    /**
     * @return array{email: string, exp: int, nonce: string, redirect?: string}
     */
    public static function verify(string $secret, string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new RuntimeException('malformed');
        }
        [$body, $signature] = $parts;

        if (! hash_equals(self::encode(hash_hmac('sha256', $body, $secret, true)), $signature)) {
            throw new RuntimeException('bad signature');
        }

        $claims = json_decode(self::decode($body), true);
        if (! is_array($claims) || ! is_string($claims['email'] ?? null) || ! is_int($claims['exp'] ?? null) || ! is_string($claims['nonce'] ?? null)) {
            throw new RuntimeException('bad claims');
        }

        $now = time();
        if ($claims['exp'] < $now || $claims['exp'] > $now + self::MAX_TTL) {
            throw new RuntimeException('expired');
        }

        $redirect = $claims['redirect'] ?? null;
        if ($redirect !== null && (! is_string($redirect) || ! str_starts_with($redirect, '/') || str_starts_with($redirect, '//') || str_contains($redirect, '\\'))) {
            throw new RuntimeException('bad redirect');
        }

        return $claims;
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
