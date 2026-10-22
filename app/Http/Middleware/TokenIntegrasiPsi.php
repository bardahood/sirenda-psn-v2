<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi mesin-ke-mesin untuk aplikasi PSI: header "Authorization: Bearer <PSI_TOKEN_MASUK>"
 * (perbandingan waktu-konstan) dan, bila diatur, pembatasan IP pemanggil.
 */
class TokenIntegrasiPsi
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('psn_dashboard.psi.token_masuk');
        if ($token === '') {
            return response()->json(['message' => 'Integrasi PSI belum diaktifkan (PSI_TOKEN_MASUK kosong).'], 503);
        }
        if (! hash_equals($token, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Token integrasi tidak valid.'], 401);
        }
        $ip = config('psn_dashboard.psi.ip_diizinkan');
        if ($ip && ! in_array($request->ip(), $ip, true)) {
            return response()->json(['message' => 'Alamat IP pemanggil tidak diizinkan.'], 403);
        }

        return $next($request);
    }
}
