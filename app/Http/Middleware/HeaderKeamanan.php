<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan HTTP (selaras pedoman keamanan SPBE), termasuk Content-Security-Policy.
 * CSP: semua skrip/gaya/font dari origin sendiri (dibundel Vite, tanpa CDN); 'unsafe-eval'
 * masih diperlukan build standar Alpine.js, 'unsafe-inline' untuk atribut style dinamis
 * (Alpine :style, ECharts, Leaflet). Tidak ada skrip inline. Lihat docs/kinerja-keamanan.md.
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
        if (config('psn_dashboard.keamanan.csp') && ! Vite::isRunningHot()) {
            $h->set('Content-Security-Policy', $this->csp());
        }
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        // Data dinas tidak boleh disimpan cache bersama (proxy).
        if ($request->user() && ! $h->has('Content-Disposition')) {
            $h->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    protected function csp(): string
    {
        // Tile peta (bila diisi) satu-satunya sumber gambar eksternal; subdomain {s} => wildcard.
        $tile = '';
        $url = (string) config('psn_dashboard.peta.tile_url');
        $p = parse_url(str_replace('{s}.', 'sub.', $url));
        if (isset($p['scheme'], $p['host'])) {
            $tile = ' '.$p['scheme'].'://'.(str_contains($url, '{s}.') ? '*.'.substr($p['host'], 4) : $p['host']);
        }

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:".$tile,
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
