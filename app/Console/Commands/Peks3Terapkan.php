<?php

namespace App\Console\Commands;

use App\Services\Peks3TerapkanService;
use Illuminate\Console\Command;

class Peks3Terapkan extends Command
{
    protected $signature = 'peks3:terapkan {--paksa : Terapkan sekarang walau tidak ada antrean / jeda belum lewat}';

    protected $description = 'Terapkan kiriman SIRENDA PEKS3 (basis data cermin) ke tabel & snapshot dashboard secara atomik';

    public function handle(Peks3TerapkanService $layanan): int
    {
        if (! $this->option('paksa') && ! $layanan->perlu()) {
            $this->line('Tidak ada perubahan yang siap diterapkan.');

            return self::SUCCESS;
        }

        $h = $layanan->terapkan($this->output->isVerbose() ? fn ($m) => $this->line($m) : null);
        match ($h['status']) {
            'BERHASIL' => $this->info(sprintf('Berhasil dalam %.1f detik: %d PSN, %d peringatan impor.', $h['durasi_detik'], $h['psn'], $h['peringatan'])),
            'SEDANG_BERJALAN' => $this->warn($h['pesan']),
            default => $this->error('Gagal: '.$h['pesan']),
        };

        return $h['status'] === 'GAGAL' ? self::FAILURE : self::SUCCESS;
    }
}
