<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * JWT RS256 minimal (tanpa dependensi) untuk integrasi mesin-ke-mesin.
 * Hanya algoritme RS256 yang diterima; "none"/HS* ditolak agar kunci publik tidak dapat disalahgunakan sebagai rahasia HMAC.
 */
class JwtRs256
{
    public static function buat(array $klaim, string $kunciPrivatPem): string
    {
        $kunci = openssl_pkey_get_private($kunciPrivatPem) ?: throw new InvalidArgumentException('Kunci privat tidak valid.');
        $bagian = self::b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.self::b64(json_encode($klaim));
        openssl_sign($bagian, $tanda, $kunci, OPENSSL_ALGO_SHA256);

        return $bagian.'.'.self::b64($tanda);
    }

    /** Verifikasi tanda tangan dan kembalikan klaim. Melempar InvalidArgumentException bila tidak sah. */
    public static function verifikasi(string $jwt, string $kunciPublikPem): array
    {
        $bagian = explode('.', $jwt);
        if (count($bagian) !== 3) {
            throw new InvalidArgumentException('Format JWT tidak valid.');
        }
        [$h, $p, $s] = $bagian;
        $header = json_decode((string) self::d64($h), true);
        if (($header['alg'] ?? null) !== 'RS256') {
            throw new InvalidArgumentException('Algoritme JWT harus RS256.');
        }
        $kunci = openssl_pkey_get_public($kunciPublikPem) ?: throw new InvalidArgumentException('Kunci publik tidak valid.');
        if (openssl_verify($h.'.'.$p, (string) self::d64($s), $kunci, OPENSSL_ALGO_SHA256) !== 1) {
            throw new InvalidArgumentException('Tanda tangan JWT tidak sah.');
        }
        $klaim = json_decode((string) self::d64($p), true);

        return is_array($klaim) ? $klaim : throw new InvalidArgumentException('Klaim JWT tidak valid.');
    }

    protected static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    protected static function d64(string $s): string|false
    {
        return base64_decode(strtr($s, '-_', '+/'), true);
    }
}
