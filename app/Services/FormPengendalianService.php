<?php

namespace App\Services;

use App\Models\PeriodeCutoff;
use App\Models\Psn;
use App\Models\SnapshotPsn;
use App\Support\Pendanaan\KebutuhanPendanaan;
use Illuminate\Support\Facades\DB;

/**
 * Data "Form Pengendalian PSN" (evaluasi kinerja PSN berjalan, format Dit. PSN Bappenas 2026).
 * Butir yang tersedia di SIRENDA diisi otomatis; angka progres & pembiayaan dibaca dari snapshot
 * cut-off. Butir tanpa sumber data (jenis fisik/non-fisik, perizinan, lahan, milestone, tipologi isu,
 * keberlanjutan status, pengesahan) dibiarkan kosong untuk dilengkapi PIC.
 */
class FormPengendalianService
{
    public const TAHAPAN = [
        'PENYIAPAN' => ['Penyiapan', 'studi kelayakan / desain proyek; desain program, regulasi, kelembagaan'],
        'TRANSAKSI' => ['Transaksi', 'pengadaan badan usaha / kontraktor / pelaksana, kerja sama'],
        'KONSTRUKSI' => ['Konstruksi', 'pembangunan fisik; sarana / sistem pendukung program'],
        'BEROPERASI_SEBAGIAN' => ['Beroperasi sebagian', 'sebagian aset / layanan sudah berjalan'],
        'KUMULATIF_SELESAI' => ['Kumulatif selesai', 'seluruh output / sasaran tercapai'],
    ];

    public function data(Psn $psn, ?PeriodeCutoff $c): array
    {
        $psn->loadMissing(['klaster', 'statusPsn', 'lokasi.provinsi', 'lokasi.kabupaten', 'sumberDana.sumberDana',
            'kelembagaan.penanggungJawab', 'kelembagaan.instansi']);
        $cfg = config('psn_dashboard.form_pengendalian');
        $snap = $c ? SnapshotPsn::where('periode_cutoff_id', $c->id)->where('psn_id', $psn->id)->first() : null;
        $miliar = fn ($v) => $v === null ? null : (float) $v / 1e9;
        $kebutuhan = KebutuhanPendanaan::pilih(KebutuhanPendanaan::indikasi([$psn->id])[$psn->id] ?? null, $psn->nilai_investasi_rp);

        $peran = fn (string $p) => $psn->kelembagaan->where('peran', $p)->sortBy('urutan')
            ->map(fn ($k) => $k->penanggungJawab?->nama ?? $k->instansi?->nama ?? $k->nama_teks)->filter()->unique()->values()->all();

        $regulasi = DB::table('regulasi')->where('psn_id', $psn->id)->whereNull('deleted_at')->orderBy('id')->get(['nama', 'tahap']);

        return [
            'cutoff' => $c,
            'triwulan' => $c ? ['I', 'II', 'III', 'IV'][intdiv($c->tanggal_cutoff->month - 1, 3)] : null,
            'akhir_triwulan' => $c && $c->tanggal_cutoff->month % 3 === 0,
            'batas_kembali' => $cfg['batas_kembali'],
            'a' => [
                'nama' => $psn->nama,
                'kode' => $psn->kode_psn,
                'klaster' => $psn->klaster?->nama,
                'lokasi' => $psn->lokasi->map(fn ($l) => $l->provinsi_kode === '00' ? 'Nasional'
                    : trim(($l->provinsi?->nama ?? $l->provinsi_kode).($l->kabupaten ? ' – '.$l->kabupaten->nama : '')))->unique()->values()->all(),
                'kelembagaan' => array_filter(['Penanggung jawab' => $peran('PENANGGUNG_JAWAB'), 'Pengelola' => $peran('PENGELOLA'), 'Pelaksana' => $peran('PELAKSANA')]),
                'dasar_penetapan' => $psn->dasar_penetapan,
                'tahun_terbit' => $psn->tahun_terbit,
                'tahun_selesai' => $psn->tahun_selesai ?? $psn->ket_tahun_selesai,
                'sumber_dana' => $psn->sumberDana->map(fn ($d) => $d->sumberDana?->nama)->filter()->unique()->values()->all(),
                'nilai_investasi_rp' => $psn->nilai_investasi_rp !== null ? (float) $psn->nilai_investasi_rp : null,
                'investasi_anomali' => (bool) $psn->investasi_anomali,
                // A9: jumlah indikasi pendanaan bila dirinci, bila belum nilai investasi (App\Support\Pendanaan\KebutuhanPendanaan).
                'kebutuhan_rp' => $kebutuhan['nilai'],
                'kebutuhan_catatan' => KebutuhanPendanaan::catatan($kebutuhan, (bool) $psn->investasi_anomali),
                'deskripsi' => $psn->deskripsi,
                'output' => $psn->output,
            ],
            'tahapan' => $cfg['tahapan'][(string) $psn->statusPsn?->kode] ?? null,
            'b2' => [
                'progres_rencana' => $snap?->progres_rencana_persen !== null ? (float) $snap->progres_rencana_persen : null,
                'progres_realisasi' => $snap?->progres_realisasi_persen !== null ? (float) $snap->progres_realisasi_persen : null,
                'pembiayaan_akhir' => $miliar($kebutuhan['nilai']),
                'pembiayaan_target' => $miliar($snap?->pagu_rp),
                'pembiayaan_realisasi' => $miliar($snap?->realisasi_anggaran_rp),
                'regulasi_total' => $regulasi->count(),
                'regulasi_ditetapkan' => $regulasi->where('tahap', 'DITETAPKAN')->count(),
                'regulasi_belum' => $regulasi->where('tahap', '<>', 'DITETAPKAN')->pluck('nama')->all(),
                'ro' => $snap ? [$snap->jumlah_ro_tercapai, $snap->jumlah_ro] : null,
            ],
            'isu' => DB::table('isu as i')->leftJoin('kegiatan as k', 'k.id', '=', 'i.kegiatan_id')
                ->where('i.psn_id', $psn->id)->whereNull('i.deleted_at')->where('i.status', '<>', 'SELESAI')
                ->orderByRaw('COALESCE(k.is_critical_path, 0) DESC')->orderByRaw('i.tenggat IS NULL')->orderBy('i.tenggat')->orderBy('i.id')
                ->limit($cfg['maks_isu'])
                ->get(['i.uraian', 'i.tindak_lanjut', 'i.kebutuhan_dukungan', 'i.pic_nama', 'i.tenggat', 'k.is_critical_path'])
                ->map(fn ($i) => [
                    'uraian' => $i->uraian,
                    'upaya' => $i->tindak_lanjut,
                    'dukungan' => trim(($i->kebutuhan_dukungan ?? '').($i->pic_nama ? " (PIC: {$i->pic_nama})" : '')) ?: null,
                    'jalur_kritis' => $i->is_critical_path === null ? null : (bool) $i->is_critical_path,
                ])->all(),
        ];
    }

    /** Cut-off bawaan: cut-off terbit terakhir di akhir triwulan; bila tidak ada, cut-off terbit terakhir. */
    public function cutoffBawaan(): ?PeriodeCutoff
    {
        $terbit = PeriodeCutoff::where('status', 'TERBIT')->orderByDesc('tanggal_cutoff');

        return (clone $terbit)->get()->first(fn ($c) => $c->tanggal_cutoff->month % 3 === 0) ?? $terbit->first();
    }
}
