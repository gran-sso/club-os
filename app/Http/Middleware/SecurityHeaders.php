<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Attach strict defensive HTTP security headers to all web responses.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Anti-Clickjacking: prevent the site from being framed on third-party domains
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME-sniffing attacks (e.g. executing user-uploaded images as scripts)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Protect internal referrer paths and tokens from leaking to external websites
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Block rogue browser API access (microphone, camera, usb, geolocation)
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Force HTTPS transport security in production / secure contexts
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}

