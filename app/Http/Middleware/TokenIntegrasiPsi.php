<?php

namespace App\Http\Middleware;

use App\Support\JwtRs256;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi mesin-ke-mesin untuk aplikasi PSI. Header "Authorization: Bearer <...>" berisi salah satu:
 *  - JWT RS256 yang ditandatangani kunci privat klien PSI (diverifikasi dengan PSI_KUNCI_PUBLIK; klaim iss/sub = PSI_CLIENT_ID,
 *    iat, exp, jti sekali pakai), atau
 *  - token statis PSI_TOKEN_MASUK (perbandingan waktu-konstan).
 * Bila diatur, alamat IP pemanggil juga dibatasi. Kontrak: docs/integrasi-psi.md §2.
 */
class TokenIntegrasiPsi
{
    public function handle(Request $request, Closure $next): Response
    {
        $cfg = config('psn_dashboard.psi');
        $token = (string) $cfg['token_masuk'];
        $kunci = $this->kunciPublik();
        if ($token === '' && $kunci === null) {
            return response()->json(['message' => 'Integrasi PSI belum diaktifkan (PSI_KUNCI_PUBLIK dan PSI_TOKEN_MASUK kosong).'], 503);
        }

        $bearer = (string) $request->bearerToken();
        $sah = $token !== '' && hash_equals($token, $bearer);
        if (! $sah && $kunci !== null && substr_count($bearer, '.') === 2) {
            try {
                $this->periksaJwt($bearer, $kunci, $cfg);
                $sah = true;
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => 'Token integrasi tidak valid: '.$e->getMessage()], 401);
            }
        }
        if (! $sah) {
            return response()->json(['message' => 'Token integrasi tidak valid.'], 401);
        }

        $ip = $cfg['ip_diizinkan'];
        if ($ip && ! in_array($request->ip(), $ip, true)) {
            return response()->json(['message' => 'Alamat IP pemanggil tidak diizinkan.'], 403);
        }

        return $next($request);
    }

    protected function kunciPublik(): ?string
    {
        $path = (string) config('psn_dashboard.psi.kunci_publik');
        if ($path === '') {
            return null;
        }
        $path = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) ? $path : base_path($path);

        return is_readable($path) ? file_get_contents($path) : null;
    }

    protected function periksaJwt(string $jwt, string $kunci, array $cfg): void
    {
        $k = JwtRs256::verifikasi($jwt, $kunci);
        $klien = (string) $cfg['client_id'];
        if ($klien === '' || ! in_array($klien, [$k['iss'] ?? null, $k['sub'] ?? null], true)) {
            throw new InvalidArgumentException('client ID tidak dikenal.');
        }

        $kini = time();
        $toleransi = (int) $cfg['jwt_toleransi_detik'];
        if (! is_int($k['iat'] ?? null) || ! is_int($k['exp'] ?? null)) {
            throw new InvalidArgumentException('klaim iat dan exp wajib.');
        }
        if ((int) $cfg['jwt_umur_maks_detik'] < $k['exp'] - $k['iat']) {
            throw new InvalidArgumentException('masa berlaku token terlalu panjang.');
        }
        if ($k['iat'] > $kini + $toleransi || $k['exp'] < $kini - $toleransi) {
            throw new InvalidArgumentException('token kedaluwarsa atau belum berlaku.');
        }
        if ($cfg['jwt_audiens'] && isset($k['aud']) && ! in_array($cfg['jwt_audiens'], (array) $k['aud'], true)) {
            throw new InvalidArgumentException('audiens token salah.');
        }

        // jti sekali pakai: token yang tersadap tidak dapat diputar ulang dengan isi lain.
        $jti = $k['jti'] ?? null;
        if (! is_string($jti) || $jti === '' || strlen($jti) > 128) {
            throw new InvalidArgumentException('klaim jti wajib.');
        }
        if (! Cache::add('psi-jti:'.hash('sha256', $klien.'|'.$jti), 1, max(60, $k['exp'] - $kini + $toleransi))) {
            throw new InvalidArgumentException('token sudah pernah dipakai (jti).');
        }
    }
}
