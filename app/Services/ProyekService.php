<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\PeriodeCutoff;
use App\Models\Psn;
use App\Models\SnapshotPsn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data Detail Proyek (tanpa cache -- selalu kondisi terkini tabel inti, kecuali
 * status & kurva S yang dibaca dari snapshot cut-off terbit).
 */
class ProyekService
{
    public const PERAN = [
        'PENGUSUL' => 'Pengusul', 'PENANGGUNG_JAWAB' => 'Penanggung jawab', 'PELAKSANA' => 'Pelaksana',
        'PENGELOLA' => 'Pengelola', 'KONTRAKTOR' => 'Kontraktor', 'SUPERVISI' => 'Supervisi',
    ];

    public function header(Psn $psn, ?PeriodeCutoff $c): array
    {
        $psn->loadMissing(['klaster', 'statusPsn', 'kelembagaan.penanggungJawab']);
        $snap = $c ? SnapshotPsn::where('periode_cutoff_id', $c->id)->where('psn_id', $psn->id)->first() : null;

        $kl = $psn->kelembagaan->where('peran', 'PENANGGUNG_JAWAB')
            ->map(fn ($k) => $k->penanggungJawab?->nama ?? $k->nama_teks)->filter()->values();

        return [
            'id' => $psn->id,
            'nama' => $psn->nama,
            'kode' => $psn->kode_psn,
            'klaster' => $psn->klaster?->nama,
            'status_psn' => $psn->statusPsn?->nama,
            'kementerian_lembaga' => $kl->all(),
            'status_progres' => $snap?->status_progres,
            'is_kritis' => (bool) $snap?->is_kritis,
            'pembaruan_terakhir' => $this->pembaruanTerakhir($psn)?->toIso8601String(),
            'cutoff' => $c?->kode,
        ];
    }

    /** Waktu perubahan terakhir pada PSN atau data turunannya (jejak audit). */
    public function pembaruanTerakhir(Psn $psn): ?Carbon
    {
        $audit = AuditLog::where('psn_id', $psn->id)->max('created_at');
        $kandidat = array_filter([$psn->updated_at, $audit ? Carbon::parse($audit) : null]);

        return $kandidat ? max($kandidat) : null;
    }

    public function profil(Psn $psn): array
    {
        $psn->loadMissing(['klaster', 'subKlaster', 'klasterPkpn', 'program', 'statusPsn', 'kelembagaan.penanggungJawab', 'kelembagaan.instansi',
            'lokasi.provinsi', 'lokasi.kabupaten', 'sumberDana.sumberDana', 'unitPengampu.unitKerja', 'profilItem.bagian']);

        return [
            'gambaran_umum' => [
                'Kode PSN' => $psn->kode_psn,
                'Kode KRISNA' => $psn->kode_krisna,
                'Kode RKP' => $psn->kode_rkp,
                'Klaster' => $psn->klaster?->nama,
                'Sub klaster' => $psn->subKlaster?->nama,
                'Klaster PKPN' => $psn->klasterPkpn?->nama,
                'Program' => $psn->program?->nama,
                'Status PSN' => $psn->statusPsn?->nama,
                'Tahun target selesai' => $psn->tahun_selesai,
                'Nilai investasi' => $psn->nilai_investasi_rp !== null ? 'Rp '.number_format($psn->nilai_investasi_rp / 1e12, 2, ',', '.').' triliun'.($psn->investasi_anomali ? ' (ditandai anomali, perlu verifikasi)' : '') : null,
                'Rencana investasi' => $psn->rencana_investasi_rp !== null ? 'Rp '.number_format($psn->rencana_investasi_rp / 1e12, 2, ',', '.').' triliun' : null,
            ],
            'narasi' => array_filter(['Deskripsi' => $psn->deskripsi, 'Output' => $psn->output, 'Dampak' => $psn->dampak, 'Dasar penetapan' => $psn->dasar_penetapan]),
            'kelembagaan' => collect(self::PERAN)->map(fn ($label, $peran) => [
                'peran' => $label,
                'nama' => $psn->kelembagaan->where('peran', $peran)->sortBy('urutan')
                    ->map(fn ($k) => $k->penanggungJawab?->nama ?? $k->instansi?->nama ?? $k->nama_teks)->filter()->values()->all(),
            ])->filter(fn ($k) => $k['nama'])->values()->all(),
            'lokasi' => $psn->lokasi->map(fn ($l) => trim(($l->provinsi?->nama ?? $l->provinsi_kode).($l->kabupaten ? ' — '.$l->kabupaten->nama : '')))->unique()->values()->all(),
            'sumber_dana' => $psn->sumberDana->map(fn ($d) => $d->sumberDana?->nama)->filter()->values()->all(),
            'unit_pengampu' => $psn->unitPengampu->map(fn ($u) => $u->unitKerja?->nama)->filter()->values()->all(),
            'item_profil' => $psn->profilItem->sortBy(fn ($i) => $i->bagian?->urutan)->groupBy(fn ($i) => $i->bagian?->nama ?? 'Lainnya')
                ->map(fn ($g) => $g->pluck('isi')->filter()->values()->all())->filter()->all(),
        ];
    }

    /** Hierarki KP/RO -> critical path, dengan status snapshot cut-off. */
    public function kegiatan(Psn $psn, ?PeriodeCutoff $c): array
    {
        $snap = $c ? DB::table('snapshot_kegiatan')->where('periode_cutoff_id', $c->id)->where('psn_id', $psn->id)->get()->keyBy('kegiatan_id') : collect();
        $semua = Kegiatan::where('psn_id', $psn->id)->with('jenis')->orderBy('id')->get();

        $bentuk = fn (Kegiatan $k) => [
            'id' => $k->id,
            'nama' => $k->nama,
            'jenis' => $k->jenis?->nama,
            'is_critical_path' => $k->is_critical_path,
            'lokasi' => $k->lokasi,
            'satuan' => $k->satuan_1,
            'target_akhir' => $k->target_akhir_1 !== null ? (float) $k->target_akhir_1 : null,
            'status_teks' => $k->status_teks,
            'status_progres' => $snap[$k->id]->status_progres ?? null,
            'deviasi_pp' => isset($snap[$k->id]) && $snap[$k->id]->deviasi_pp !== null ? (float) $snap[$k->id]->deviasi_pp : null,
        ];

        return $semua->whereNull('parent_id')->map(fn ($k) => $bentuk($k) + [
            'turunan' => $semua->where('parent_id', $k->id)->map($bentuk)->values()->all(),
        ])->values()->all();
    }

    /**
     * Tab Progres: kurva S (snapshot terbit tahun berjalan), ringkasan deviasi,
     * tabel KP/RO, isu terbuka, jejak audit.
     */
    public function progres(Psn $psn, ?PeriodeCutoff $c): array
    {
        $tahun = $c?->tanggal_cutoff->year ?? now()->year;
        $kurva = array_fill(1, 12, ['rencana_persen' => null, 'realisasi_persen' => null]);
        if ($c) {
            DB::table('snapshot_psn as s')->join('periode_cutoff as c', 'c.id', '=', 's.periode_cutoff_id')
                ->where('s.psn_id', $psn->id)->where('c.status', 'TERBIT')->whereYear('c.tanggal_cutoff', $tahun)
                ->where('c.tanggal_cutoff', '<=', $c->tanggal_cutoff)
                ->get(['c.tanggal_cutoff', 's.progres_rencana_persen', 's.progres_realisasi_persen'])
                ->each(function ($r) use (&$kurva) {
                    $kurva[(int) substr($r->tanggal_cutoff, 5, 2)] = [
                        'rencana_persen' => $r->progres_rencana_persen !== null ? (float) $r->progres_rencana_persen : null,
                        'realisasi_persen' => $r->progres_realisasi_persen !== null ? (float) $r->progres_realisasi_persen : null,
                    ];
                });
        }

        $snap = $c ? SnapshotPsn::where('periode_cutoff_id', $c->id)->where('psn_id', $psn->id)->first() : null;

        return [
            'tahun' => $tahun,
            'kurva_s' => collect($kurva)->map(fn ($v, $m) => ['bulan' => $m] + $v)->values()->all(),
            'ringkasan' => $snap ? [
                'rencana_persen' => $snap->progres_rencana_persen !== null ? (float) $snap->progres_rencana_persen : null,
                'realisasi_persen' => $snap->progres_realisasi_persen !== null ? (float) $snap->progres_realisasi_persen : null,
                'deviasi_pp' => $snap->deviasi_pp !== null ? (float) $snap->deviasi_pp : null,
                'status_progres' => $snap->status_progres,
                'pagu_rp' => $snap->pagu_rp !== null ? (float) $snap->pagu_rp : null,
                'realisasi_anggaran_rp' => $snap->realisasi_anggaran_rp !== null ? (float) $snap->realisasi_anggaran_rp : null,
                'jumlah_ro' => $snap->jumlah_ro,
                'jumlah_ro_tercapai' => $snap->jumlah_ro_tercapai,
                'pembaruan_terakhir' => $snap->pembaruan_terakhir_at?->toDateString(),
            ] : null,
            'ro' => $this->tabelRo($psn, $c, $tahun),
            'isu_terbuka' => DB::table('isu')->where('psn_id', $psn->id)->whereNull('deleted_at')->where('status', '<>', 'SELESAI')
                ->orderByRaw('tenggat IS NULL')->orderBy('tenggat')->limit(20)
                ->get(['id', 'uraian', 'kebutuhan_dukungan', 'pic_nama', 'tenggat', 'status'])
                ->map(fn ($i) => (array) $i + ['lewat_tenggat' => $i->tenggat !== null && $i->tenggat < now()->toDateString()])->all(),
            'jejak_audit' => $this->jejakAudit($psn, 15),
        ];
    }

    protected function tabelRo(Psn $psn, ?PeriodeCutoff $c, int $tahun): array
    {
        $snap = $c ? DB::table('snapshot_kegiatan')->where('periode_cutoff_id', $c->id)->where('psn_id', $psn->id)->get()->keyBy('kegiatan_id') : collect();

        // Bukti pelaporan: berkas pada baris periode terakhir yang memilikinya.
        $bukti = DB::table('kegiatan_target as t')->join('kegiatan as k', 'k.id', '=', 't.kegiatan_id')
            ->where('k.psn_id', $psn->id)->where('t.tahun', $tahun)->whereNotNull('t.bukti_path')
            ->orderBy('t.periode_ke')->pluck('t.bukti_path', 't.kegiatan_id');

        return Kegiatan::where('psn_id', $psn->id)->orderByDesc('is_critical_path')->orderBy('id')->get()
            ->filter(fn ($k) => isset($snap[$k->id]))
            ->map(fn ($k) => [
                'id' => $k->id,
                'nama' => $k->nama,
                'is_critical_path' => $k->is_critical_path,
                'target_persen' => $snap[$k->id]->target_persen !== null ? (float) $snap[$k->id]->target_persen : null,
                'realisasi_persen' => $snap[$k->id]->realisasi_persen !== null ? (float) $snap[$k->id]->realisasi_persen : null,
                'deviasi_pp' => $snap[$k->id]->deviasi_pp !== null ? (float) $snap[$k->id]->deviasi_pp : null,
                'status_progres' => $snap[$k->id]->status_progres,
                'bukti' => $bukti[$k->id] ?? null,
            ])->values()->all();
    }

    public function jejakAudit(Psn $psn, int $limit = 50): array
    {
        return AuditLog::where('psn_id', $psn->id)->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get()
            ->map(fn ($a) => [
                'waktu' => $a->created_at?->toIso8601String(),
                'pengguna' => $a->user_label ?? 'Sistem',
                'aksi' => $a->aksi,
                'tabel' => $a->tabel,
                'kolom' => array_keys($a->nilai_baru ?? $a->nilai_lama ?? []),
                'nilai_lama' => $a->nilai_lama,
                'nilai_baru' => $a->nilai_baru,
                'sumber' => $a->sumber,
            ])->all();
    }

    /** Ringkasan untuk JSON GET /api/v1/proyek/{id}. */
    public function semua(Psn $psn, ?PeriodeCutoff $c): array
    {
        return [
            'header' => $this->header($psn, $c),
            'profil' => $this->profil($psn),
            'kegiatan' => $this->kegiatan($psn, $c),
            'progres' => $this->progres($psn, $c),
        ];
    }
}
