<?php

namespace App\Exports;

use App\Models\PeriodeCutoff;
use App\Services\PengisianService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Rekap status pengisian & verifikasi seluruh PSN aktif pada snapshot cut-off. */
class RekapPengisianExport implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(protected PeriodeCutoff $c) {}

    public function collection(): Collection
    {
        $batas = app(PengisianService::class)->batasPengisian($this->c)->endOfDay();
        $unit = DB::table('psn_unit_pengampu as pu')->join('ref_unit_kerja as u', 'u.id', '=', 'pu.unit_kerja_id')
            ->orderBy('u.nama')->get(['pu.psn_id', 'u.nama'])->groupBy('psn_id');

        // Baris PSN dari snapshot (bukan tabel inti) agar cakupan sama dengan angka cut-off.
        return DB::table('snapshot_psn as s')->join('psn as p', 'p.id', '=', 's.psn_id')
            ->leftJoin('pengisian_psn as g', fn ($j) => $j->on('g.psn_id', '=', 's.psn_id')->where('g.periode_cutoff_id', $this->c->id))
            ->leftJoin('users as ua', 'ua.id', '=', 'g.diajukan_oleh')->leftJoin('users as uv', 'uv.id', '=', 'g.diverifikasi_oleh')
            ->where('s.periode_cutoff_id', $this->c->id)->where('s.is_aktif', true)
            ->orderBy('p.nama')->orderBy('p.id')
            ->get(['p.id', 'p.kode_psn', 'p.nama', 'g.status', 'g.diajukan_at', 'ua.name as pengaju', 'g.diverifikasi_at', 'uv.name as verifikator', 'g.catatan_verifikator'])
            ->map(fn ($r) => [
                $r->id, $r->kode_psn, $r->nama, ($unit[$r->id] ?? collect())->pluck('nama')->implode(', '),
                PengisianService::STATUS[$r->status ?? 'BELUM'], $r->diajukan_at, $r->pengaju,
                $r->diajukan_at === null ? '' : ($r->diajukan_at <= $batas->toDateTimeString() ? 'Tepat waktu' : 'Terlambat'),
                $r->diverifikasi_at, $r->verifikator, $r->catatan_verifikator,
            ]);
    }

    public function headings(): array
    {
        return ['ID', 'Kode', 'Nama PSN', 'Unit pengampu', 'Status pengisian', 'Diajukan', 'Diajukan oleh', 'Ketepatan', 'Ditinjau', 'Verifikator', 'Catatan verifikator'];
    }

    public function title(): string
    {
        return 'Pengisian '.$this->c->kode;
    }
}
