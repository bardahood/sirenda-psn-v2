<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Telusuri satu PSN di sepanjang alur sinkron PEKS3 -> dashboard: basis data PEKS3 asli (opsional, server sama),
 * basis data cermin, kiriman yang diterima, penerapan terakhir, tabel psn dashboard, dan snapshot per cut-off.
 */
class PsnCekSinkron extends Command
{
    protected $signature = 'psn:cek-sinkron {kode : Kode PSN, mis. R.5-2026.1-07} {--sumber= : Nama basis data PEKS3 asli di server yang sama, mis. sirendapsn_bappenas}';

    protected $description = 'Periksa di tahap mana sebuah PSN tertahan dalam sinkron SIRENDA PEKS3 ke dashboard';

    public function handle(): int
    {
        $kode = trim($this->argument('kode'));
        $pola = '%'.str_replace(['%', '_'], ['\%', '\_'], $kode).'%';
        $kolom = ['id', 'kode_psn', 'proyek_psn', 'status', 'deleted_at', 'updated_at'];
        $legacyId = null;

        if ($sumber = $this->option('sumber')) {
            $this->line("== 1. Basis data PEKS3 asli ({$sumber})");
            try {
                $rows = DB::connection('legacy')->table("{$sumber}.psn")->where('kode_psn', 'like', $pola)->get($kolom);
                $this->tabel($rows);
                $legacyId ??= $rows->first()?->id;
            } catch (Throwable $e) {
                $this->warn('Tidak dapat dibaca: '.mb_substr($e->getMessage(), 0, 200));
            }
        }

        $this->line('== 2. Basis data cermin (koneksi legacy: '.config('database.connections.legacy.database').')');
        try {
            $rows = DB::connection('legacy')->table('psn')->where('kode_psn', 'like', $pola)->get($kolom);
            $this->tabel($rows);
            $legacyId = $rows->first()?->id ?? $legacyId;
            if (! $rows->count()) {
                $this->warn('PSN TIDAK ADA di cermin: kiriman dari SIRENDA PEKS3 belum masuk (lihat bagian 3).');
            }
        } catch (Throwable $e) {
            $this->warn('Cermin tidak dapat dibaca: '.mb_substr($e->getMessage(), 0, 200));
        }

        $this->line('== 3. Kiriman & penerapan');
        $s = DB::table('integrasi_peks3_status')->find(1);
        $this->line('Penerapan terakhir: '.($s->diterapkan_at ?? 'belum pernah').' · tertunda sejak: '.($s->tertunda_sejak ?? '-')
            .' · kiriman terakhir: '.(DB::table('integrasi_peks3_log')->where('jenis', '<>', 'TERAPKAN')->max('created_at') ?? 'belum pernah'));
        if ($s?->galat) {
            $this->warn('Galat penerapan terakhir: '.mb_substr($s->galat, 0, 400));
        }
        if ($legacyId) {
            $this->tabel(DB::table('integrasi_peks3_log')->where('legacy_psn_id', $legacyId)->orderByDesc('id')->limit(5)->get(['created_at', 'jenis', 'hasil', 'pesan']),
                "Belum ada kiriman paket untuk psn.id {$legacyId} dari SIRENDA PEKS3.");
        }

        $this->line('== 4. Tabel psn dashboard');
        $psn = DB::table('psn as p')->leftJoin('ref_status_psn as s', 's.id', '=', 'p.status_psn_id')
            ->where(fn ($q) => $q->where('p.kode_psn', 'like', $pola)->when($legacyId, fn ($w) => $w->orWhere('p.legacy_id', $legacyId)))
            ->get(['p.id', 'p.kode_psn', 'p.legacy_id', 's.nama as status', 's.is_aktif', 'p.deleted_at']);
        $this->tabel($psn, 'PSN TIDAK ADA di dashboard.');

        if ($psn->count()) {
            $this->line('== 5. Snapshot per cut-off (dasar Dashboard & Portofolio)');
            $this->tabel(DB::table('periode_cutoff as c')->leftJoin('snapshot_psn as sp', fn ($j) => $j->on('sp.periode_cutoff_id', '=', 'c.id')->where('sp.psn_id', $psn->first()->id))
                ->orderBy('c.kode')->get(['c.kode', 'c.status', 'sp.id as ada_snapshot', 'sp.is_aktif', 'sp.sumber']));
        }

        return self::SUCCESS;
    }

    protected function tabel($rows, string $kosong = '(tidak ada baris)'): void
    {
        if (! count($rows)) {
            $this->line($kosong);

            return;
        }
        $rows = collect($rows)->map(fn ($r) => array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, 60) : $v, (array) $r));
        $this->table(array_keys($rows->first()), $rows->all());
    }
}
