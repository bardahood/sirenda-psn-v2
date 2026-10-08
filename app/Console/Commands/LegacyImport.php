<?php

namespace App\Console\Commands;

use App\Support\Legacy\LegacyImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class LegacyImport extends Command
{
    protected $signature = 'legacy:import
        {--fresh : Kosongkan tabel data (dan pengguna hasil impor) sebelum impor}
        {--only= : Jalankan langkah tertentu saja, dipisah koma (referensi,pengguna,psn,kinerja,kegiatan,risiko,monev,usulan,riwayat)}';

    protected $description = 'Impor data dari basis data SIRENDA PSN lama (koneksi `legacy`) ke skema v2';

    public function handle(LegacyImporter $importer): int
    {
        $langkah = $this->option('only')
            ? array_map('trim', explode(',', $this->option('only')))
            : LegacyImporter::LANGKAH;

        if ($salah = array_diff($langkah, LegacyImporter::LANGKAH)) {
            $this->error('Langkah tidak dikenal: '.implode(', ', $salah));

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            if (! $this->confirm('Semua data PSN di basis data v2 akan dihapus lalu diimpor ulang. Lanjutkan?', ! $this->input->isInteractive())) {
                return self::FAILURE;
            }
            $importer->kosongkan();
        }

        $laporan = $importer->onLog(fn ($m) => $this->line($m))->jalankan($langkah);

        $this->table(['Tabel', 'Baris'], collect($laporan['jumlah'])->map(fn ($n, $t) => [$t, $n])->values());
        foreach ($laporan['peringatan'] as $jenis => $daftar) {
            $this->warn(sprintf('%-22s %4d peringatan, contoh: %s', $jenis, count($daftar), $daftar[0]));
        }

        $path = 'etl/laporan-'.now()->format('Ymd-His').'.json';
        Storage::put($path, json_encode($laporan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info('Laporan lengkap: '.Storage::path($path));

        return self::SUCCESS;
    }
}
