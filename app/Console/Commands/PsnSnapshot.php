<?php

namespace App\Console\Commands;

use App\Services\SnapshotService;
use Illuminate\Console\Command;
use RuntimeException;

class PsnSnapshot extends Command
{
    protected $signature = 'psn:snapshot
        {cutoff? : Periode YYYY-MM (bawaan: bulan lalu)}
        {--tanggal= : Tanggal cut-off (bawaan: akhir bulan periode)}
        {--terbit : Terbitkan snapshot (dipakai dashboard sebagai pembanding & membatalkan cache)}
        {--paksa : Bangun ulang snapshot yang sudah terbit}';

    protected $description = 'Bekukan nilai progres, anggaran, status, risiko, dan kelengkapan PSN per tanggal cut-off';

    public function handle(SnapshotService $service): int
    {
        $cutoff = $this->argument('cutoff') ?? now()->subMonthNoOverflow()->format('Y-m');

        try {
            $hasil = $service->buat($cutoff, $this->option('tanggal'), (bool) $this->option('terbit'), (bool) $this->option('paksa'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Butir', 'Nilai'], collect($hasil)->map(fn ($v, $k) => [$k, $v])->values());

        return self::SUCCESS;
    }
}
