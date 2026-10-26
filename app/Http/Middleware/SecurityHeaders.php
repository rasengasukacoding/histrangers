<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Browser defenses. Strict CSP is scoped to /random (legacy pages have inline/CDN assets). */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(18));
        $request->attributes->set('histrangers_csp_nonce', $nonce);
        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(self), display-capture=(self), geolocation=(), payment=(), usb=()');

        // HSTS must only be sent for HTTPS deployments, never for local HTTP.
        if ($request->isSecure() && app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        if ($request->is('random') || $request->is('random/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        if ($request->is('random') && $response->headers->get('Content-Type') !== 'application/json') {
            $policy = [
                "default-src 'self'",
                "base-uri 'self'",
                "object-src 'none'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self'",
                "img-src 'self' data: blob:",
                "font-src 'self' data:",
                "media-src 'self' blob:",
                "connect-src 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
            ];
            if ($request->isSecure() && app()->environment('production')) {
                $policy[] = 'upgrade-insecure-requests';
            }
            $response->headers->set('Content-Security-Policy', implode('; ', $policy));
        }

        return $response;
    }
}
