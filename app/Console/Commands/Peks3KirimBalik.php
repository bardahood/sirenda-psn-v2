<?php

namespace App\Console\Commands;

use App\Services\Peks3KeluarService;
use Illuminate\Console\Command;

/** Kirim isian menu Pengisian ke SIRENDA PEKS3 (docs/integrasi-peks3.md §9). Dijadwalkan tiap menit. */
class Peks3KirimBalik extends Command
{
    protected $signature = 'peks3:kirim-balik {--ulang=5 : Jumlah putaran maksimal per jalan (50 operasi per putaran)}';

    protected $description = 'Kirim antrean perubahan dashboard (peks3_keluar) ke penerima di SIRENDA PEKS3';

    public function handle(Peks3KeluarService $balik): int
    {
        for ($i = 0; $i < (int) $this->option('ulang'); $i++) {
            $h = $balik->kirim();
            $this->line(json_encode($h, JSON_UNESCAPED_UNICODE));
            if ($h['status'] !== 'BERHASIL') {
                return in_array($h['status'], ['KOSONG', 'NONAKTIF'], true) ? self::SUCCESS : self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
