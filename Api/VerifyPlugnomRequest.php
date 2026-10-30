<?php

namespace App\Vito\Plugins\Plugnomdev\VitoSso\Api;

use App\Vito\Plugins\Plugnomdev\VitoSso\Secret;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-to-server calls from Plugnom's API carry
 *   X-Plugnom-Timestamp: unix seconds
 *   X-Plugnom-Signature: hex HMAC-SHA256(secret, "{timestamp}.{METHOD}.{request URI}.{raw body}")
 * and are refused if the signature is wrong or more than 5 minutes old.
 */
class VerifyPlugnomRequest
{
    private const WINDOW_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $secret = Secret::get();
        $timestamp = (string) $request->header('X-Plugnom-Timestamp', '');
        $signature = (string) $request->header('X-Plugnom-Signature', '');

        if ($secret === null || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::WINDOW_SECONDS) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $timestamp.'.'.$request->getMethod().'.'.$request->getRequestUri().'.'.$request->getContent();
        if (! hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
