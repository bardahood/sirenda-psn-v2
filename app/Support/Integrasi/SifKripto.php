<?php

namespace App\Support\Integrasi;

use InvalidArgumentException;
use RuntimeException;

/**
 * Kriptografi API SIF (Portal PSN dan KPBU, Dit. PSI), lihat docs/integrasi-psi.md §2:
 *  - Kunci sesi AES-256 (32 byte acak, sekali pakai) dibungkus RSA-OAEP dengan SHA-256 & MGF1-SHA-256,
 *    dikirim base64 di header X-Session-Key.
 *  - Isi data dienkripsi AES-256-GCM, format base64(iv[12] + ciphertext + tag[16]).
 *
 * PHP (openssl_public_encrypt) hanya menyediakan OAEP dengan SHA-1, sehingga padding OAEP-SHA256
 * disusun sesuai RFC 8017 §7.1 di atas operasi RSA mentah (OPENSSL_NO_PADDING).
 */
final class SifKripto
{
    private const H_LEN = 32; // SHA-256

    public static function kunciSesi(): string
    {
        return random_bytes(32);
    }

    /** RSA-OAEP(SHA-256, MGF1-SHA-256) dengan label kosong; hasil base64. */
    public static function bungkusKunci(string $kunciSesi, string $kunciPublikPem): string
    {
        $kunci = openssl_pkey_get_public($kunciPublikPem) ?: throw new InvalidArgumentException('Kunci publik SIF tidak valid.');
        $k = intdiv(openssl_pkey_get_details($kunci)['bits'], 8);
        $mLen = strlen($kunciSesi);
        if ($mLen > $k - 2 * self::H_LEN - 2) {
            throw new InvalidArgumentException('Pesan terlalu panjang untuk RSA-OAEP.');
        }

        $db = hash('sha256', '', true).str_repeat("\0", $k - $mLen - 2 * self::H_LEN - 2)."\x01".$kunciSesi;
        $seed = random_bytes(self::H_LEN);
        $maskedDb = $db ^ self::mgf1($seed, $k - self::H_LEN - 1);
        $maskedSeed = $seed ^ self::mgf1($maskedDb, self::H_LEN);

        if (! openssl_public_encrypt("\0".$maskedSeed.$maskedDb, $hasil, $kunci, OPENSSL_NO_PADDING)) {
            throw new RuntimeException('Gagal membungkus kunci sesi: '.openssl_error_string());
        }

        return base64_encode($hasil);
    }

    /** Kebalikan bungkusKunci() (dipakai sisi server/uji). */
    public static function bukaKunci(string $base64, string $kunciPrivatPem): string
    {
        $kunci = openssl_pkey_get_private($kunciPrivatPem) ?: throw new InvalidArgumentException('Kunci privat tidak valid.');
        $k = intdiv(openssl_pkey_get_details($kunci)['bits'], 8);
        if (! openssl_private_decrypt((string) base64_decode($base64, true), $em, $kunci, OPENSSL_NO_PADDING) || strlen($em) !== $k || $em[0] !== "\0") {
            throw new RuntimeException('Kunci sesi tidak dapat dibuka.');
        }

        $maskedSeed = substr($em, 1, self::H_LEN);
        $maskedDb = substr($em, 1 + self::H_LEN);
        $seed = $maskedSeed ^ self::mgf1($maskedDb, self::H_LEN);
        $db = $maskedDb ^ self::mgf1($seed, $k - self::H_LEN - 1);
        $pemisah = strpos($db, "\x01", self::H_LEN);
        if (! hash_equals(hash('sha256', '', true), substr($db, 0, self::H_LEN)) || $pemisah === false
            || trim(substr($db, self::H_LEN, $pemisah - self::H_LEN), "\0") !== '') {
            throw new RuntimeException('Padding OAEP tidak valid.');
        }

        return substr($db, $pemisah + 1);
    }

    /** AES-256-GCM; hasil base64(iv[12] + ciphertext + tag[16]). */
    public static function enkripsi(string $teks, string $kunciSesi): string
    {
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($teks, 'aes-256-gcm', $kunciSesi, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Gagal mengenkripsi data.');
        }

        return base64_encode($iv.$cipher.$tag);
    }

    public static function dekripsi(string $base64, string $kunciSesi): string
    {
        $raw = base64_decode($base64, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new RuntimeException('Data terenkripsi tidak valid.');
        }
        $teks = openssl_decrypt(substr($raw, 12, -16), 'aes-256-gcm', $kunciSesi, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, -16));
        if ($teks === false) {
            throw new RuntimeException('Dekripsi gagal: data rusak atau kunci sesi tidak cocok.');
        }

        return $teks;
    }

    /** Amplop body request terenkripsi (Content-Type: application/vnd.sif.encrypted+json). */
    public static function amplop(array $body, string $kunciSesi): array
    {
        return ['encrypted' => true, 'algorithm' => 'AES-256-GCM', 'format' => 'iv+ciphertext+tag',
            'data' => self::enkripsi(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $kunciSesi)];
    }

    private static function mgf1(string $seed, int $panjang): string
    {
        $t = '';
        for ($c = 0; strlen($t) < $panjang; $c++) {
            $t .= hash('sha256', $seed.pack('N', $c), true);
        }

        return substr($t, 0, $panjang);
    }
}
