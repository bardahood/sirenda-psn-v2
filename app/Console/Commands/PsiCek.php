<?php

namespace App\Console\Commands;

use App\Services\SifClient;
use App\Services\SifException;
use App\Support\Integrasi\SifKripto;
use Illuminate\Console\Command;
use Throwable;

/**
 * Diagnosis interoperabilitas SIRENDA dengan API SIF Dit. PSI, langkah demi langkah:
 * konfigurasi .env, berkas kunci publik, uji kriptografi, lalu satu panggilan nyata (GET daftar usulan).
 * Tidak mengubah data apa pun. Lihat docs/integrasi-psi.md §6.
 */
class PsiCek extends Command
{
    protected $signature = 'psi:cek
        {--kunci-privat= : (Opsional, hanya di komputer lokal) path kunci privat untuk memastikan pasangan kunci cocok}
        {--tanpa-jaringan : Lewati panggilan ke server SIF}';

    protected $description = 'Periksa konfigurasi dan koneksi integrasi API SIF (Dit. PSI) tanpa mengubah data';

    protected bool $lulus = true;

    public function handle(SifClient $sif): int
    {
        $c = config('psn_dashboard.psi.sif');

        $this->info('1. Konfigurasi .env');
        $this->hasil(filled($c['url']), 'SIF_BASE_URL', $c['url'] ?: 'kosong (minta alamat host SIF ke Dit. PSI)');
        if (filled($c['url']) && ! str_starts_with((string) $c['url'], 'https://')) {
            $this->warn('   ! SIF_BASE_URL sebaiknya diawali https://');
        }
        if (filled($c['url']) && ! str_ends_with(rtrim((string) $c['url'], '/'), '/api/v1/external/usulan-psn')) {
            $this->warn('   ! SIF_BASE_URL biasanya berakhiran /api/v1/external/usulan-psn');
        }
        $this->hasil(filled($c['api_key']), 'SIF_API_KEY', filled($c['api_key']) ? 'terisi ('.strlen($c['api_key']).' karakter)' : 'kosong (minta X-API-Key ke Dit. PSI)');
        $this->hasil(filled($c['client_id']), 'SIF_CLIENT_ID', $c['client_id'] ?: 'kosong (mis. konsultan-psn-snkv93)');

        $this->info('2. Berkas kunci publik');
        $pem = $this->bacaKunci((string) $c['kunci_publik']);
        if ($pem !== null) {
            $this->line('   Fingerprint SHA-256: '.$this->sidikJari($pem).' (cocokkan dengan Dit. PSI bila perlu)');
        }

        $this->info('3. Uji kriptografi (RSA-OAEP SHA-256 + AES-256-GCM)');
        if ($pem !== null) {
            $this->ujiKripto($pem);
        } else {
            $this->hasil(false, 'Kriptografi', 'dilewati karena kunci publik belum valid');
        }

        $this->info('4. Koneksi ke server SIF');
        if ($this->option('tanpa-jaringan')) {
            $this->line('   - dilewati (--tanpa-jaringan)');
        } elseif ($k = $sif->kendala()) {
            $this->hasil(false, 'Koneksi', 'dilewati: '.$k);
        } else {
            $this->ujiKoneksi($sif);
        }

        $this->newLine();
        $this->lulus
            ? $this->info('SEMUA PEMERIKSAAN LULUS. Jalankan: php artisan psi:sinkron')
            : $this->error('Ada pemeriksaan yang gagal. Perbaiki butir bertanda [GAGAL] lalu jalankan ulang: php artisan psi:cek');

        return $this->lulus ? self::SUCCESS : self::FAILURE;
    }

    protected function bacaKunci(string $path): ?string
    {
        $penuh = $path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) ? $path : base_path($path);
        if ($penuh === '' || ! is_file($penuh)) {
            $this->hasil(false, 'SIF_KUNCI_PUBLIK', "berkas tidak ditemukan: {$penuh}");

            return null;
        }
        if (! is_readable($penuh)) {
            $this->hasil(false, 'SIF_KUNCI_PUBLIK', "berkas tidak dapat dibaca (periksa izin berkas): {$penuh}");

            return null;
        }
        $isi = (string) file_get_contents($penuh);
        if (str_contains($isi, 'PRIVATE KEY')) {
            $this->hasil(false, 'SIF_KUNCI_PUBLIK', 'berkas berisi KUNCI PRIVAT. Ganti dengan kunci publik (BEGIN PUBLIC KEY) dan hapus kunci privat dari server.');

            return null;
        }
        $kunci = @openssl_pkey_get_public($isi);
        $d = $kunci ? openssl_pkey_get_details($kunci) : null;
        if (! $d || $d['type'] !== OPENSSL_KEYTYPE_RSA) {
            $this->hasil(false, 'SIF_KUNCI_PUBLIK', 'isi berkas bukan kunci publik RSA PEM yang valid');

            return null;
        }
        $this->hasil($d['bits'] >= 2048, 'SIF_KUNCI_PUBLIK', "RSA {$d['bits']} bit, {$penuh}");

        return $d['bits'] >= 2048 ? $isi : null;
    }

    protected function ujiKripto(string $pem): void
    {
        try {
            $sesi = SifKripto::kunciSesi();
            $bungkus = SifKripto::bungkusKunci($sesi, $pem);
            $ok = SifKripto::dekripsi(SifKripto::enkripsi('{"uji":true}', $sesi), $sesi) === '{"uji":true}';
            $this->hasil($ok && $bungkus !== '', 'Enkripsi', 'kunci sesi berhasil dibungkus dan AES-256-GCM bolak-balik');
        } catch (Throwable $e) {
            $this->hasil(false, 'Enkripsi', $e->getMessage());

            return;
        }

        if ($p = $this->option('kunci-privat')) {
            $privat = is_readable($p) ? (string) file_get_contents($p) : '';
            try {
                $this->hasil(SifKripto::bukaKunci($bungkus, $privat) === $sesi, 'Pasangan kunci', 'kunci privat dapat membuka kunci sesi (pasangan cocok)');
            } catch (Throwable $e) {
                $this->hasil(false, 'Pasangan kunci', 'kunci privat TIDAK cocok dengan kunci publik: '.$e->getMessage());
            }
        }
    }

    protected function ujiKoneksi(SifClient $sif): void
    {
        $mulai = microtime(true);
        try {
            $r = $sif->daftar(1);
            $ms = (int) ((microtime(true) - $mulai) * 1000);
            $jumlah = $r['meta']['total'] ?? (is_array($r['data'] ?? null) ? count($r['data']) : '?');
            $this->hasil(true, 'Koneksi', "berhasil ({$ms} ms). Usulan submitted/under_review terbaca: {$jumlah}");
        } catch (SifException $e) {
            $this->hasil(false, 'Koneksi', $e->getMessage());
            $this->line('   Arti: '.$this->artiStatus($e->getCode()));
        } catch (Throwable $e) {
            $this->hasil(false, 'Koneksi', $e->getMessage());
            $this->line('   Arti: server SIF tidak terjangkau dari server ini (alamat salah, DNS, firewall, atau IP server SIRENDA belum didaftarkan di Dit. PSI).');
        }
    }

    protected function artiStatus(int $status): string
    {
        return match (true) {
            $status === 401 => 'SIF_API_KEY salah, kedaluwarsa, atau belum diaktifkan oleh Dit. PSI.',
            $status === 403 => 'Ditolak: SIF_CLIENT_ID tidak dikenal, kunci publik tidak cocok dengan yang terdaftar di SIF, endpoint belum diizinkan untuk client ini, atau IP server belum didaftarkan.',
            $status === 404 => 'Alamat tidak ditemukan: periksa SIF_BASE_URL (harus berakhiran /api/v1/external/usulan-psn).',
            $status === 415 => 'Format body ditolak; laporkan ke pengembang SIRENDA.',
            $status === 429 => 'Batas 60 request per menit terlampaui; tunggu satu menit lalu ulangi.',
            $status >= 500 => 'Gangguan di server SIF; hubungi Dit. PSI.',
            default => 'Lihat pesan di atas.',
        };
    }

    protected function sidikJari(string $pem): string
    {
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pem));

        return implode(':', str_split(strtoupper(hash('sha256', $der)), 2));
    }

    protected function hasil(bool $ok, string $butir, string $ket): void
    {
        $this->lulus = $this->lulus && $ok;
        $this->line(sprintf('   %s %s: %s', $ok ? '<fg=green>[OK]</>' : '<fg=red>[GAGAL]</>', $butir, $ket));
    }
}
