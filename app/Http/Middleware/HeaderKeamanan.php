<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan HTTP dasar (selaras pedoman keamanan SPBE). CSP belum dipasang
 * karena build standar Alpine.js memerlukan 'unsafe-eval'; lihat docs/kinerja-keamanan.md.
 */
class HeaderKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;
        $h->set('X-Frame-Options', 'DENY');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        // Data dinas tidak boleh disimpan cache bersama (proxy).
        if ($request->user() && ! $h->has('Content-Disposition')) {
            $h->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
