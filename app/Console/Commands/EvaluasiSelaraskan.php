<?php

namespace App\Console\Commands;

use App\Services\EvaluasiStatusService;
use Illuminate\Console\Command;

/** Selaraskan status otomatis Evaluasi Status PSN (form kembali bila isian wajib lengkap; kumulatif selesai). */
class EvaluasiSelaraskan extends Command
{
    protected $signature = 'evaluasi:selaraskan';

    protected $description = 'Buat catatan evaluasi untuk PSN yang isian wajibnya lengkap dan tetapkan status otomatis (docs/evaluasi-status-psn.md)';

    public function handle(EvaluasiStatusService $layanan): int
    {
        $h = $layanan->selaraskan();
        $this->info("Form kembali dicatat: {$h['dibuat']} · Siap Dianalisis otomatis: {$h['siap']} · dikembalikan ke pengecekan: {$h['dikembalikan']}");

        return self::SUCCESS;
    }
}
