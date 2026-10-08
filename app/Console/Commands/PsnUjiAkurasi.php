<?php

namespace App\Console\Commands;

use App\Models\PeriodeCutoff;
use App\Support\Akurasi\UjiAkurasi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PsnUjiAkurasi extends Command
{
    protected $signature = 'psn:uji-akurasi
        {periode? : Cut-off terbit YYYY-MM (bawaan: terbaru)}
        {--filter=* : Kombinasi filter tambahan dalam bentuk query string, mis. "prov=31&kat=psn"}
        {--hanya-selisih : Tampilkan hanya baris yang tidak cocok}';

    protected $description = 'Bandingkan angka dashboard K1-K4 & P1-P7 dengan query SQL acuan untuk berbagai kombinasi filter';

    public function handle(UjiAkurasi $uji): int
    {
        $c = $this->argument('periode')
            ? PeriodeCutoff::where('kode', $this->argument('periode'))->where('status', 'TERBIT')->first()
            : PeriodeCutoff::terbaru();

        if (! $c) {
            $this->error('Cut-off terbit tidak ditemukan.');

            return self::FAILURE;
        }

        Cache::flush(); // bandingkan hasil hitung segar, bukan cache
        $hasil = $uji->jalankan($c, [...$uji->kombinasiBawaan($c), ...$this->option('filter')]);
        $tampil = $this->option('hanya-selisih') ? array_filter($hasil, fn ($r) => ! $r['cocok']) : $hasil;
        $ringkas = fn ($v) => is_array($v) ? (count($v) > 4 ? count($v).' nilai' : json_encode($v, JSON_UNESCAPED_UNICODE)) : var_export($v, true);

        $this->table(['Indikator', 'Filter', 'Aplikasi', 'Acuan SQL', 'Hasil'], array_map(fn ($r) => [
            $r['indikator'], $r['filter'], $ringkas($r['aplikasi']), $ringkas($r['acuan']), $r['cocok'] ? 'COCOK' : 'SELISIH',
        ], $tampil));

        $selisih = count(array_filter($hasil, fn ($r) => ! $r['cocok']));
        $pesan = sprintf('Cut-off %s: %d pemeriksaan, %d cocok, %d selisih.', $c->kode, count($hasil), count($hasil) - $selisih, $selisih);
        $selisih ? $this->error($pesan) : $this->info($pesan);

        return $selisih ? self::FAILURE : self::SUCCESS;
    }
}
