<?php

namespace App\Services;

use App\Enums\LevelRisiko;
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
                'Tahun penetapan PSN' => $psn->tahun_terbit,
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
            // Nama sumber dana beserta nominalnya bila dirinci di SIRENDA (psn_pendanaan.nilai).
            'sumber_dana' => $psn->sumberDana->filter(fn ($d) => $d->sumberDana)->map(fn ($d) => $d->sumberDana->nama
                .($d->nilai_rp > 0 ? ' · Rp '.number_format($d->nilai_rp / 1e12, 2, ',', '.').' triliun' : ''))->values()->all(),
            'sumber_dana_catatan' => IkhtisarPortofolioService::nominalKonsisten((float) $psn->sumberDana->sum('nilai_rp'), $psn->nilai_investasi_rp) ? null
                : 'Jumlah nominal per sumber dana melebihi nilai investasi; kemungkinan total investasi ditulis di setiap sumber. Mohon diperiksa di SIRENDA.',
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
    // ------------------------------------------------------------------ tab Perencanaan

    /**
     * Rencana & sasaran PSN: target tahunan, indikator kinerja, penerima manfaat, kontribusi Trisula, SDGs, dukungan
     * yang dibutuhkan, fasilitas kemudahan yang pernah diterima, asal usulan (bila PSN berasal dari usulan PSI),
     * dan ringkasan evaluasi keberlanjutan status. Semua dari data terkini (isian SIRENDA PEKS3).
     */
    public function perencanaan(Psn $psn): array
    {
        $tahun = range(2026, 2029);
        $target = fn (string $tabel, string $fk, array $id) => DB::table($tabel)->whereIn($fk, $id)
            ->when($tabel === 'trisula_target', fn ($q) => $q->where('periode', 'TAHUNAN'))
            ->get([$fk, 'tahun', 'target', 'realisasi'])->groupBy($fk)
            ->map(fn ($g) => $g->keyBy('tahun')->map(fn ($t) => ['target' => $this->angkaAtauNull($t->target), 'realisasi' => $this->angkaAtauNull($t->realisasi)]));
        $baris = function (string $tabel, string $targetTabel, string $fk, ?\Closure $tambah = null) use ($psn, $target, $tahun) {
            $rows = DB::table($tabel)->where('psn_id', $psn->id)->whereNull('deleted_at')->orderBy('id')->get();
            $t = $target($targetTabel, $fk, $rows->pluck('id')->all());

            return $rows->map(fn ($r) => [
                'uraian' => $r->uraian ?? $r->indikator ?? null, 'satuan' => $r->satuan,
                'baseline' => $this->angkaAtauNull($r->baseline), 'baseline_tahun' => $r->baseline_tahun,
                'target_akhir' => $this->angkaAtauNull($r->target_akhir), 'capaian' => $this->angkaAtauNull($r->capaian),
                'persen' => $r->target_akhir > 0 && $r->capaian !== null ? round($r->capaian / $r->target_akhir * 100, 1) : null,
                'tahun' => collect($tahun)->mapWithKeys(fn ($th) => [$th => $t[$r->id][$th] ?? ['target' => null, 'realisasi' => null]])->all(),
            ] + ($tambah ? $tambah($r) : []))->values()->all();
        };
        $kategoriTrisula = DB::table('ref_kode')->where('tipe', 'KTAC')->pluck('nama', 'id');

        $usulan = DB::table('usulan_psn as u')->where('u.psn_id', $psn->id)->whereNull('u.deleted_at')
            ->leftJoin('penilaian as p', fn ($j) => $j->on('p.usulan_id', '=', 'u.id')
                ->whereRaw('p.id = (SELECT MAX(p2.id) FROM penilaian p2 WHERE p2.usulan_id = u.id AND p2.deleted_at IS NULL)'))
            ->leftJoin('usulan_rekomendasi as r', fn ($j) => $j->on('r.usulan_id', '=', 'u.id')->whereNull('r.deleted_at'))
            ->orderByDesc('u.tahun_rkp')->get(['u.id', 'u.nama', 'u.tahun_rkp', 'u.status', 'p.nilai_akhir', 'p.rekomendasi as rekomendasi_skor', 'p.status as status_penilaian',
                'r.rekomendasi', 'r.status as status_rekomendasi']);
        $evaluasi = DB::table('evaluasi_psn')->where('psn_id', $psn->id)->whereNull('deleted_at')
            ->where('tahun_rkp', config('psn_dashboard.evaluasi_status.tahun_rkp'))->first(['status_akhir', 'd1_kebutuhan', 'indikasi_status']);

        return [
            'tahun' => $tahun,
            'target_tahunan' => DB::table('psn_target_tahunan')->where('psn_id', $psn->id)->whereNull('deleted_at')->orderBy('tahun')->get(['tahun', 'uraian'])
                ->map(fn ($t) => ['tahun' => $t->tahun, 'uraian' => $t->uraian])->all(),
            'indikator' => $baris('indikator', 'indikator_target', 'indikator_id'),
            'penerima' => $baris('penerima_manfaat', 'penerima_manfaat_target', 'penerima_manfaat_id'),
            'trisula' => $baris('trisula', 'trisula_target', 'trisula_id', fn ($r) => ['kategori' => $kategoriTrisula[$r->kategori_id] ?? 'Tanpa kategori', 'kontribusi' => $r->kontribusi]),
            'sdgs' => DB::table('psn_sdgs as s')->join('ref_kode as k', 'k.id', '=', 's.sdgs_id')->where('s.psn_id', $psn->id)
                ->orderBy('k.urutan')->orderBy('k.kode')->get(['k.kode', 'k.nama', 's.keterangan'])->map(fn ($s) => (array) $s)->all(),
            'dukungan' => DB::table('psn_dukungan')->where('psn_id', $psn->id)->whereNull('deleted_at')->orderBy('id')->pluck('uraian')->all(),
            'fasilitas' => DB::table('psn_fasilitas as f')->join('ref_kode as k', 'k.id', '=', 'f.fasilitas_id')->where('f.psn_id', $psn->id)->whereNull('f.deleted_at')
                ->orderBy('k.kode')->orderBy('f.tahun')->get(['k.nama', 'f.tahun', 'f.keterangan'])->groupBy('nama')
                ->map(fn ($g, $nama) => ['nama' => $nama, 'tahun' => $g->pluck('tahun')->filter()->unique()->values()->all(), 'keterangan' => $g->pluck('keterangan')->filter()->unique()->implode('; ')])->values()->all(),
            'usulan' => $usulan->unique('id')->map(fn ($u) => (array) $u)->values()->all(),
            'evaluasi' => $evaluasi ? (array) $evaluasi : null,
        ];
    }

    // ------------------------------------------------------------------ tab Risiko & Isu

    /** Register risiko PSN (inheren -> residual harapan -> aktual pemantauan terakhir) beserta perlakuan, dan seluruh isu. */
    public function risikoIsu(Psn $psn): array
    {
        $risiko = DB::table('risiko as r')->leftJoin('ref_kode as k', 'k.id', '=', 'r.kategori_id')->leftJoin('kegiatan as g', 'g.id', '=', 'r.kegiatan_id')
            ->where('r.psn_id', $psn->id)->whereNull('r.deleted_at')
            ->get(['r.*', 'k.nama as kategori', 'g.nama as kegiatan', 'g.is_critical_path']);
        $id = $risiko->pluck('id')->all();
        $perlakuan = DB::table('risiko_perlakuan as p')->leftJoin('ref_instansi as i', 'i.id', '=', 'p.instansi_id')->whereIn('p.risiko_id', $id)
            ->orderBy('p.id')->get(['p.risiko_id', 'p.uraian', 'i.nama as instansi', 'p.keterangan'])->groupBy('risiko_id');
        $tahun = DB::table('risiko_tahun_perlakuan')->whereIn('risiko_id', $id)->orderBy('tahun')->get()->groupBy('risiko_id');
        $pantau = DB::table('risiko_pemantauan')->whereIn('risiko_id', $id)->orderByDesc('tanggal')->orderByDesc('id')->get()->unique('risiko_id')->keyBy('risiko_id');
        $urut = fn (?string $l) => $l ? (LevelRisiko::tryFrom($l)?->urutan() ?? 0) : 0;

        $daftar = $risiko->map(function ($r) use ($perlakuan, $tahun, $pantau) {
            $a = $pantau[$r->id] ?? null;

            return [
                'id' => $r->id, 'uraian' => $r->uraian, 'kategori' => $r->kategori, 'kegiatan' => $r->kegiatan, 'critical_path' => (bool) $r->is_critical_path,
                'titik_kritis' => (bool) $r->is_titik_kritis,
                'awal' => ['level' => $r->level_awal, 'kemungkinan' => $r->kemungkinan_awal, 'dampak' => $r->dampak_awal],
                'harapan' => ['level' => $r->level_harapan, 'kemungkinan' => $r->kemungkinan_harapan, 'dampak' => $r->dampak_harapan],
                'aktual' => $a ? ['level' => $a->level_aktual, 'kemungkinan' => $a->kemungkinan_aktual, 'dampak' => $a->dampak_aktual,
                    'tanggal' => $a->tanggal, 'progres' => $a->progres_persen !== null ? (float) $a->progres_persen : null, 'status' => $a->status_perlakuan] : null,
                'rencana' => $r->rencana_perlakuan, 'pj' => $r->penanggung_jawab, 'catatan' => $r->catatan,
                'perlakuan' => collect($perlakuan[$r->id] ?? [])->map(fn ($p) => ['uraian' => $p->uraian, 'instansi' => $p->instansi, 'keterangan' => $p->keterangan])->all(),
                'tahun' => collect($tahun[$r->id] ?? [])->pluck('tahun')->all(),
            ];
        })
            // Entri identik (uraian, kategori, level, rencana, PJ, KP/RO sama) digabung; tahun perlakuan disatukan.
            ->groupBy(fn ($r) => md5(json_encode([$r['uraian'], $r['kategori'], $r['kegiatan'], $r['awal'], $r['harapan'], $r['aktual']['level'] ?? null, $r['rencana'], $r['pj'], $r['perlakuan']])))
            ->map(fn ($g) => $g->first() + ['jumlah' => $g->count()] + ['tahun_gabung' => $g->flatMap(fn ($r) => $r['tahun'])->unique()->sort()->values()->all()])
            ->map(fn ($r) => array_merge($r, ['tahun' => $r['tahun_gabung']]))
            ->sort(fn ($x, $y) => [$urut($y['aktual']['level'] ?? $y['harapan']['level']), $urut($y['harapan']['level']), $x['id']]
            <=> [$urut($x['aktual']['level'] ?? $x['harapan']['level']), $urut($x['harapan']['level']), $y['id']])->values();

        $hariIni = now()->toDateString();
        $isu = DB::table('isu as i')->leftJoin('kegiatan as g', 'g.id', '=', 'i.kegiatan_id')->leftJoin('ref_unit_kerja as u', 'u.id', '=', 'i.pic_unit_kerja_id')
            ->where('i.psn_id', $psn->id)->whereNull('i.deleted_at')
            ->orderByRaw("i.status = 'SELESAI'")->orderByRaw('i.tenggat IS NULL')->orderBy('i.tenggat')->orderBy('i.id')
            ->get(['i.*', 'g.nama as kegiatan', 'g.is_critical_path', 'u.nama as pic_unit'])
            ->map(fn ($i) => [
                'id' => $i->id, 'uraian' => $i->uraian, 'kebutuhan_dukungan' => $i->kebutuhan_dukungan, 'kegiatan' => $i->kegiatan, 'critical_path' => (bool) $i->is_critical_path,
                'pic' => $i->pic_nama ?? $i->pic_unit, 'tenggat' => $i->tenggat, 'status' => $i->status, 'tanggal_selesai' => $i->tanggal_selesai,
                'tindak_lanjut' => $i->tindak_lanjut, 'file_bukti' => $i->file_bukti,
                'lewat_tenggat' => $i->tenggat !== null && $i->tenggat < $hariIni && $i->status !== 'SELESAI',
            ]);

        return [
            'risiko' => $daftar->all(),
            'jumlah_risiko' => $daftar->sum('jumlah'),
            'per_level' => collect(LevelRisiko::cases())->map(fn ($l) => ['level' => $l->value,
                'harapan' => $daftar->where('harapan.level', $l->value)->sum('jumlah'), 'aktual' => $daftar->where('aktual.level', $l->value)->sum('jumlah')])->all(),
            'kritis_min' => config('psn_dashboard.risiko.kritis_min_level'),
            'isu' => $isu->all(),
            'isu_ringkas' => ['terbuka' => $isu->where('status', '<>', 'SELESAI')->count(), 'lewat_tenggat' => $isu->where('lewat_tenggat', true)->count(),
                'jalur_kritis' => $isu->where('status', '<>', 'SELESAI')->where('critical_path', true)->count(), 'selesai' => $isu->where('status', 'SELESAI')->count()],
        ];
    }

    // ------------------------------------------------------------------ tab Regulasi

    /** Kebutuhan regulasi PSN: tahap pipeline, target tahun penyelesaian, penanggung jawab, penetapan. */
    public function regulasi(Psn $psn): array
    {
        $rows = DB::table('regulasi')->where('psn_id', $psn->id)->whereNull('deleted_at')->orderBy('id')->get();
        $tahun = DB::table('regulasi_target_tahun')->whereIn('regulasi_id', $rows->pluck('id'))->orderBy('tahun')->get()->groupBy('regulasi_id');
        $tahap = RisikoService::TAHAP_REGULASI;

        return [
            'tahap' => collect($tahap)->map(fn ($l, $k) => ['kode' => $k, 'label' => $l, 'jumlah' => $rows->where('tahap', $k)->count()])->values()->all(),
            'regulasi' => $rows->map(fn ($r) => [
                'id' => $r->id, 'nama' => $r->nama, 'justifikasi' => $r->justifikasi, 'pj' => $r->penanggung_jawab, 'tahap' => $r->tahap, 'tahap_label' => $tahap[$r->tahap] ?? $r->tahap,
                'tahap_ke' => array_search($r->tahap, array_keys($tahap), true), 'nomor' => $r->nomor_penetapan, 'tanggal' => $r->tanggal_penetapan, 'catatan' => $r->catatan,
                'target_tahun' => collect($tahun[$r->id] ?? [])->pluck('tahun')->all(),
            ])->all(),
        ];
    }

    // ------------------------------------------------------------------ tab Stakeholder

    /**
     * Pemetaan stakeholder (jenis A) dan kerangka kelembagaan (jenis B) dari SIRENDA. Kolom instansi berisi kode
     * master INST (mis. "145") yang diterjemahkan ke nama instansi; teks bebas ditampilkan apa adanya.
     */
    public function stakeholder(Psn $psn): array
    {
        $rows = DB::table('psn_stakeholder as s')->leftJoin('ref_kode as k', 'k.id', '=', 's.peran_id')
            ->where('s.psn_id', $psn->id)->whereNull('s.deleted_at')->orderBy('s.jenis')->orderBy('k.kode')->orderBy('s.id')
            ->get(['s.*', 'k.nama as kategori']);
        $kode = $rows->flatMap(fn ($r) => array_merge($this->kodeInstansi($r->instansi_utama), $this->kodeInstansi($r->instansi_pendukung)))->unique()->values();
        // Kode 3 digit = master INST (K/L); kode "53" / "53.14" = wilayah (pemerintah daerah provinsi/kabupaten/kota).
        $instansi = DB::table('ref_instansi')->whereIn('kode', $kode)->pluck('nama', 'kode');
        $wilayah = DB::table('ref_wilayah')->whereIn('kode', $kode)->get(['kode', 'nama', 'level'])->keyBy('kode');
        $namaKode = fn (string $k) => $instansi[$k] ?? (isset($wilayah[$k]) ? ($wilayah[$k]->level == 1 ? 'PEMERINTAH PROVINSI ' : 'PEMERINTAH ').$wilayah[$k]->nama : $k);
        $nama = fn (?string $v) => collect($this->kodeInstansi($v))->map($namaKode)->implode('; ') ?: ($v !== null && trim($v) !== '' ? trim($v) : null);
        $bentuk = fn ($r) => ['kategori' => $r->kategori ?? 'Lainnya', 'peran' => $r->peran, 'instansi_utama' => $nama($r->instansi_utama),
            'instansi_pendukung' => $nama($r->instansi_pendukung), 'keterangan' => $r->keterangan, 'catatan' => $r->catatan, 'level' => $r->level, 'file_bukti' => $r->file_bukti];
        $kelompok = fn (string $jenis) => $rows->where('jenis', $jenis)->map($bentuk)->groupBy('kategori')
            ->map(fn ($g, $k) => ['kategori' => $k, 'baris' => $g->values()->all()])->values()->all();

        return [
            'pemetaan' => $kelompok('A'),
            'kelembagaan' => $kelompok('B'),
            'lainnya' => $rows->whereNotIn('jenis', ['A', 'B'])->map($bentuk)->values()->all(),
            'jumlah_instansi' => $rows->map(fn ($r) => $nama($r->instansi_utama))->filter()->unique()->count(),
        ];
    }

    /** "145" / "145, 056" / '["145","056"]' / "53.14" -> daftar kode instansi atau wilayah; teks bebas -> []. */
    protected function kodeInstansi(?string $v): array
    {
        $v = trim((string) $v);
        if ($v === '') {
            return [];
        }
        $daftar = str_starts_with($v, '[') ? (json_decode($v, true) ?: []) : preg_split('/[;,]\s*/', $v);

        return collect($daftar)->map(fn ($x) => trim((string) $x))->every(fn ($x) => preg_match('/^(\d{3}|\d{2}(\.\d{2})?)$/', $x)) ? array_values(array_map('trim', $daftar)) : [];
    }

    protected function angkaAtauNull($v): ?float
    {
        return $v === null ? null : (float) $v;
    }

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
