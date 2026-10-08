<?php

namespace App\Http\Controllers;

use App\Enums\StatusProgres;
use App\Services\DashboardService;
use App\Support\Dashboard\FilterGlobal;
use App\Support\Dashboard\KamusIndikator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    /** PDF Ringkasan Eksekutif sesuai cut-off & filter global (angka sama dengan dashboard). */
    public function ringkasanPdf(Request $r, DashboardService $d)
    {
        $f = FilterGlobal::fromRequest($r);
        $c = $d->cutoff($f) ?? abort(404, 'Belum ada cut-off yang diterbitkan.');

        $data = [
            'cutoff' => $c,
            'sebelumnya' => $c->sebelumnya(),
            'filter' => $this->labelFilter($f, $d),
            'kamus' => KamusIndikator::DAFTAR,
            'kpi' => collect($d->kpi($c, $f))->keyBy('kode'),
            'progres' => $d->progres($c, $f),
            'klaster' => $d->distribusi($c, $f, 'klaster'),
            'provinsi' => array_slice($d->distribusi($c, $f, 'provinsi'), 0, 10),
            'dana' => $d->distribusi($c, $f, 'dana'),
            'tahapan' => $d->tahapan($c, $f),
            'roKritis' => $d->roKritis($c, $f),
            'statusData' => $d->statusData($c, $f),
            'dibuat' => now(),
            'oleh' => $r->user()->name,
        ];

        return Pdf::loadView('laporan.ringkasan-pdf', $data)->setPaper('a4', 'portrait')
            ->download("ringkasan-eksekutif-psn_{$c->kode}.pdf");
    }

    /** @return array<string,string> label filter yang aktif untuk dicetak di laporan */
    protected function labelFilter(FilterGlobal $f, DashboardService $d): array
    {
        $nama = fn (string $t, string $kunci, array $ids) => DB::table($t)->whereIn($kunci, $ids)->pluck('nama')->implode(', ');

        return array_filter([
            'Provinsi' => $f->prov ? $nama('ref_wilayah', 'kode', $f->prov) : null,
            'Klaster' => $f->klaster ? $nama('ref_klaster', 'id', $f->klaster) : null,
            'Direktorat' => $f->dit ? $nama('ref_unit_kerja', 'id', $f->dit) : null,
            'Status' => $f->status ? implode(', ', array_map(fn ($s) => StatusProgres::from($s)->label(), $f->status)) : null,
            'Kategori' => $f->kat,
            'Sumber dana' => $f->dana ? implode(', ', array_map(fn ($s) => DashboardService::LABEL_SKEMA[$s] ?? $s, $f->dana)) : null,
        ]);
    }
}
