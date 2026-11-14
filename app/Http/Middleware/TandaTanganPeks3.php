<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi kiriman SIRENDA PEKS3 (docs/integrasi-peks3.md §3):
 *   X-Client-Id  = PEKS3_CLIENT_ID
 *   X-Timestamp  = detik epoch saat mengirim (selisih maks. toleransi_detik)
 *   X-Signature  = "sha256=" . hex(HMAC-SHA256(PEKS3_RAHASIA, X-Timestamp . "." . body mentah))
 * Tanda tangan yang sama hanya diterima sekali (anti-replay). IP pengirim dapat dibatasi.
 */
class TandaTanganPeks3
{
    public function handle(Request $request, Closure $next): Response
    {
        $cfg = config('psn_dashboard.peks3');
        $rahasia = (string) $cfg['rahasia'];
        if (! $cfg['aktif'] || strlen($rahasia) < 32) {
            return $this->tolak(503, 'Sinkron PEKS3 belum diaktifkan (PEKS3_AKTIF / PEKS3_RAHASIA min. 32 karakter).');
        }
        if ($cfg['ip_diizinkan'] && ! in_array($request->ip(), $cfg['ip_diizinkan'], true)) {
            return $this->tolak(403, 'Alamat IP pengirim tidak diizinkan.');
        }
        if (! hash_equals((string) $cfg['client_id'], (string) $request->header('X-Client-Id'))) {
            return $this->tolak(401, 'X-Client-Id tidak dikenal.');
        }

        $waktu = (string) $request->header('X-Timestamp');
        if (! ctype_digit($waktu) || abs(time() - (int) $waktu) > (int) $cfg['toleransi_detik']) {
            return $this->tolak(401, 'X-Timestamp kosong atau selisih waktu terlalu besar; periksa jam server pengirim.');
        }

        $tanda = (string) $request->header('X-Signature');
        $harap = 'sha256='.hash_hmac('sha256', $waktu.'.'.$request->getContent(), $rahasia);
        if (! hash_equals($harap, $tanda)) {
            return $this->tolak(401, 'X-Signature tidak valid.');
        }
        if (! Cache::add('peks3:tanda:'.hash('sha256', $tanda), 1, 2 * (int) $cfg['toleransi_detik'])) {
            return $this->tolak(409, 'Permintaan yang sama sudah diterima (replay).');
        }

        return $next($request);
    }

    protected function tolak(int $status, string $pesan): Response
    {
        return response()->json(['status' => 'error', 'message' => $pesan], $status);
    }
}
