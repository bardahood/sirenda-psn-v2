<?php

namespace App\Console\Commands;

use App\Services\SifClient;
use App\Services\SifSinkronService;
use Illuminate\Console\Command;

class PsiSinkron extends Command
{
    protected $signature = 'psi:sinkron {--belum-dinilai : Hanya usulan yang belum punya skor PMO di SIF}';

    protected $description = 'Ambil usulan PSN (submitted/under_review) dari API SIF Dit. PSI ke menu Perencanaan';

    public function handle(SifClient $sif, SifSinkronService $sinkron): int
    {
        if ($k = $sif->kendala()) {
            $this->error($k);

            return self::FAILURE;
        }

        $h = $sinkron->sinkron((bool) $this->option('belum-dinilai'), fn ($m) => $this->line($m));
        $this->info(sprintf('Selesai: %d baru, %d diperbarui, %d tetap, %d gagal.', $h['baru'], $h['diperbarui'], $h['tetap'], $h['gagal']));
        foreach ($h['galat'] as $g) {
            $this->warn($g);
        }

        return $h['gagal'] ? self::FAILURE : self::SUCCESS;
    }
}
