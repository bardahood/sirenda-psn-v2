<?php

namespace App\Console\Commands;

use App\Support\JwtRs256;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Membuat JWT RS256 seperti yang akan dikirim aplikasi PSI, untuk uji coba API masuk dengan curl/Postman.
 * Kunci privat hanya dibaca dari berkas lokal dan tidak disimpan.
 */
class PsiTokenUji extends Command
{
    protected $signature = 'psi:token-uji {kunci_privat : Path berkas PEM kunci privat klien PSI} {--umur=300 : Masa berlaku token (detik)}';

    protected $description = 'Membuat JWT uji (RS256) untuk memanggil API integrasi PSI';

    public function handle(): int
    {
        $path = $this->argument('kunci_privat');
        $klien = (string) config('psn_dashboard.psi.client_id');
        if (! is_readable($path) || $klien === '') {
            $this->error(! is_readable($path) ? "Berkas kunci tidak dapat dibaca: {$path}" : 'PSI_CLIENT_ID belum diatur di .env.');

            return self::FAILURE;
        }

        $kini = time();
        $this->line(JwtRs256::buat([
            'iss' => $klien, 'sub' => $klien, 'iat' => $kini,
            'exp' => $kini + min((int) $this->option('umur'), (int) config('psn_dashboard.psi.jwt_umur_maks_detik')),
            'jti' => (string) Str::uuid(),
        ] + array_filter(['aud' => config('psn_dashboard.psi.jwt_audiens')]), file_get_contents($path)));
        $this->comment('Token sekali pakai (jti). Buat token baru untuk setiap permintaan.', 'v');

        return self::SUCCESS;
    }
}
