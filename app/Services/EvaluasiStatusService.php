<?php

namespace App\Services;

use App\Models\EvaluasiPsn;
use App\Models\PeriodeCutoff;
use App\Models\Psn;
use App\Support\Evaluasi\KebutuhanStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Evaluasi keberlanjutan status PSN (kertas kerja "Input Form Pengendalian PSN 2026"):
 *  - kelengkapan: aturan wajib isi (sheet "Aturan Wajib Isi") dengan data A-C dibaca dari profil PSN
 *    (snapshot cut-off terbit terakhir, isu, kegiatan, manfaat) dan D1-D3 dari isian evaluasi;
 *  - indikator & rekomendasi sistem sebagai alat bantu analis (keputusan tetap pada Indikasi Status);
 *  - status akhir, riwayat, dan data visual pemantauan. Lihat docs/evaluasi-status-psn.md.
 */
class EvaluasiStatusService
{
    protected ?PeriodeCutoff $cutoff = null;

    protected bool $cutoffDimuat = false;

    public function tahun(): int
    {
        return (int) config('psn_dashboard.evaluasi_status.tahun_rkp');
    }

    public function cutoff(): ?PeriodeCutoff
    {
        if (! $this->cutoffDimuat) {
            $this->cutoff = PeriodeCutoff::where('status', 'TERBIT')->orderByDesc('tanggal_cutoff')->first();
            $this->cutoffDimuat = true;
        }

        return $this->cutoff;
    }

    /**
     * Semua PSN aktif pada cut-off terbit terakhir (dalam cakupan pengguna) beserta hasil analisisnya.
     *
     * @return Collection<int, array>
     */
    public function daftar(?array $hanyaId = null): Collection
    {
        $c = $this->cutoff();
        if (! $c) {
            return collect();
        }
        $tahapan = config('psn_dashboard.form_pengendalian.tahapan');

        $psn = Psn::query()
            ->join('snapshot_psn as s', fn ($j) => $j->on('s.psn_id', '=', 'psn.id')->where('s.periode_cutoff_id', $c->id))
            ->leftJoin('ref_status_psn as st', 'st.id', '=', 's.status_psn_id')
            ->leftJoin('ref_klaster as k', 'k.id', '=', 's.klaster_id')
            // Seluruh PSN tercatat pada cut-off (RKP + Permenko, termasuk Keluar dari PSN), sama dengan K1 dashboard.
            ->when($hanyaId !== null, fn ($q) => $q->whereIn('psn.id', $hanyaId ?: [0]))
            // Urutan data SIRENDA PEKS3 (psn.id aplikasi lama), PSN tanpa ID lama di akhir.
            ->orderByRaw('psn.legacy_id IS NULL')->orderBy('psn.legacy_id')->orderBy('psn.id')
            ->get(['psn.id', 'psn.nama', 'psn.kode_psn', 'psn.tahun_selesai', 'psn.tahun_terbit', 'psn.nilai_investasi_rp', 's.sumber', 's.progres_rencana_persen', 's.progres_realisasi_persen',
                's.pagu_rp', 's.realisasi_anggaran_rp', 's.jumlah_ro', 's.jumlah_ro_tercapai', 's.unit_kerja_id', 's.is_aktif', 'st.kode as status_kode', 'st.nama as status_nama', 'k.nama as klaster']);
        $id = $psn->pluck('id')->all();
        if (! $id) {
            return collect();
        }

        // Isu terbuka profil (jalur kritis dulu, lalu tenggat terdekat) sebagai isian C2 bawaan.
        $maksIsu = (int) config('psn_dashboard.evaluasi_status.maks_isu');
        $isuProfil = DB::table('isu as i')->leftJoin('kegiatan as kg', 'kg.id', '=', 'i.kegiatan_id')
            ->whereIn('i.psn_id', $id)->whereNull('i.deleted_at')->where('i.status', '<>', 'SELESAI')
            ->orderBy('i.psn_id')->orderByRaw('COALESCE(kg.is_critical_path, 0) DESC')->orderByRaw('i.tenggat IS NULL')->orderBy('i.tenggat')->orderBy('i.id')
            ->get(['i.psn_id', 'i.uraian', 'i.tindak_lanjut', 'i.kebutuhan_dukungan', 'i.pic_nama', 'kg.is_critical_path'])
            ->groupBy('psn_id');
        $kegiatan = DB::table('kegiatan')->whereIn('psn_id', $id)->whereNull('deleted_at')->groupBy('psn_id')
            ->selectRaw('psn_id, COUNT(*) AS n')->pluck('n', 'psn_id');
        $manfaat = DB::table('penerima_manfaat')->whereIn('psn_id', $id)->whereNull('deleted_at')->groupBy('psn_id')->selectRaw('psn_id, COUNT(*) AS n')->pluck('n', 'psn_id');
        foreach (DB::table('indikator')->whereIn('psn_id', $id)->whereNull('deleted_at')->groupBy('psn_id')->selectRaw('psn_id, COUNT(*) AS n')->pluck('n', 'psn_id') as $p => $n) {
            $manfaat[$p] = ($manfaat[$p] ?? 0) + $n;
        }
        $regulasi = DB::table('regulasi')->whereIn('psn_id', $id)->whereNull('deleted_at')->groupBy('psn_id')
            ->selectRaw("psn_id, COUNT(*) AS total, SUM(tahap = 'DITETAPKAN') AS ditetapkan")->get()->keyBy('psn_id');
        // D1 profil: isian K/L "Kebutuhan Status PSN" di SIRENDA (psn_kebutuhan jenis B), tahun terbaru dulu.
        $kebutuhan = DB::table('psn_evaluasi_status')->whereIn('psn_id', $id)->whereNull('deleted_at')
            ->orderBy('psn_id')->orderByDesc('tahun')->orderBy('id')->get(['psn_id', 'kebutuhan_status'])
            ->groupBy('psn_id')->map(fn ($g) => $g->pluck('kebutuhan_status')->filter()->implode(' | '));
        // Rujukan D2: fasilitas kemudahan yang pernah diterima menurut SIRENDA (master FAST) -> kode D2.
        $petaFasilitas = config('psn_dashboard.evaluasi_status.peta_fasilitas');
        $fasilitas = DB::table('psn_fasilitas as f')->join('ref_kode as k', 'k.id', '=', 'f.fasilitas_id')->whereIn('f.psn_id', $id)->whereNull('f.deleted_at')
            ->orderBy('f.tahun')->get(['f.psn_id', 'f.tahun', 'k.kode'])->groupBy('psn_id')
            ->map(fn ($g) => $g->filter(fn ($f) => isset($petaFasilitas[$f->kode]))->groupBy(fn ($f) => $petaFasilitas[$f->kode])
                ->map(fn ($x) => $x->pluck('tahun')->filter()->unique()->sort()->values()->all())->all());
        $evaluasi = EvaluasiPsn::where('tahun_rkp', $this->tahun())->whereIn('psn_id', $id)
            ->with(['capaian', 'milestone', 'isu', 'lampiran'])->get()->keyBy('psn_id');

        return $psn->map(function ($p) use ($tahapan, $isuProfil, $maksIsu, $kegiatan, $manfaat, $regulasi, $evaluasi, $kebutuhan, $fasilitas) {
            $data = [
                'tahapan' => $tahapan[(string) $p->status_kode] ?? null,
                'keluar' => ! $p->is_aktif,
                'status_nama' => $p->status_nama,
                'tahun_selesai' => $p->tahun_selesai ? (int) $p->tahun_selesai : null,
                // A6 profil: tahun penetapan (tahun_terbit) di SIRENDA; isian A6 Evaluasi menimpanya bila diisi.
                'tahun_terbit' => $p->tahun_terbit ? (int) $p->tahun_terbit : null,
                // D1 profil dari teks SIRENDA (MASIH/TIDAK; null bila kosong/tidak tegas) & rujukan D2.
                'kebutuhan_status' => $kebutuhan[$p->id] ?? null,
                'd1_profil' => KebutuhanStatus::d1($kebutuhan[$p->id] ?? null),
                'fasilitas_profil' => $fasilitas[$p->id] ?? [],
                'progres_rencana' => $p->progres_rencana_persen !== null ? (float) $p->progres_rencana_persen : null,
                'progres_realisasi' => $p->progres_realisasi_persen !== null ? (float) $p->progres_realisasi_persen : null,
                'pagu_rp' => $p->pagu_rp !== null ? (float) $p->pagu_rp : null,
                'realisasi_anggaran_rp' => $p->realisasi_anggaran_rp !== null ? (float) $p->realisasi_anggaran_rp : null,
                'ro' => [(int) $p->jumlah_ro_tercapai, (int) $p->jumlah_ro],
                'isu_terbuka' => count($isuProfil[$p->id] ?? []),
                'isu_profil' => collect($isuProfil[$p->id] ?? [])->take($maksIsu)->map(fn ($i) => ['uraian' => $i->uraian, 'tipologi' => null,
                    'jalur_kritis' => $i->is_critical_path === null ? null : (bool) $i->is_critical_path, 'upaya' => $i->tindak_lanjut,
                    'dukungan' => trim(($i->kebutuhan_dukungan ?? '').($i->pic_nama ? " ({$i->pic_nama})" : '')) ?: null, 'dari_profil' => true])->values()->all(),
                'kegiatan' => (int) ($kegiatan[$p->id] ?? 0),
                'manfaat' => (int) ($manfaat[$p->id] ?? 0),
                'regulasi_total' => (int) ($regulasi[$p->id]->total ?? 0),
                'regulasi_ditetapkan' => (int) ($regulasi[$p->id]->ditetapkan ?? 0),
                'regulasi_belum' => (int) (($regulasi[$p->id]->total ?? 0) - ($regulasi[$p->id]->ditetapkan ?? 0)),
                'nilai_investasi_rp' => $p->nilai_investasi_rp !== null ? (float) $p->nilai_investasi_rp : null,
            ];
            $e = $evaluasi[$p->id] ?? null;

            return ['psn_id' => $p->id, 'nama' => $p->nama, 'kode' => $p->kode_psn, 'sumber' => $p->sumber, 'klaster' => $p->klaster,
                'evaluasi' => $e, 'status_akhir' => $e?->status_akhir ?? 'BELUM_DITERIMA'] + $this->analisis($data, $e);
        });
    }

    /** Analisis satu PSN untuk halaman detail. */
    public function satu(Psn $psn): ?array
    {
        return $this->daftar([$psn->id])->first();
    }

    /**
     * @param  array  $d  data profil (lihat daftar())
     * @return array{data:array, kurang:string[], lengkap:bool, indikator:array, rekomendasi:string, sinyal:string[]}
     */
    public function analisis(array $d, ?EvaluasiPsn $e): array
    {
        $t = $d['tahapan'];
        $berjalan = in_array($t, ['PENYIAPAN', 'TRANSAKSI', 'KONSTRUKSI', 'BEROPERASI_SEBAGIAN'], true);
        $jenis = $e?->jenis_psn ?: 'FISIK';
        $capaian = $this->capaian($d, $e);
        $isuEfektif = $this->isuEfektif($d, $e);
        $tanpaIsu = $e?->tanpa_isu || str_contains(mb_strtolower((string) $e?->c1_lainnya), 'tidak ada');
        $terisi = fn (string $k, array $kolom) => collect($kolom)->every(fn ($c) => filled($capaian[$k][$c] ?? null));
        $kurang = [];

        // Aturan wajib isi (kertas kerja sheet "Aturan Wajib Isi"). Nilai B2 = isian kertas kerja, bila kosong data profil.
        self::a6($e, $d) || $kurang[] = 'A6 Tahun penetapan PSN';
        $d['tahun_selesai'] || $kurang[] = 'A7 Target tahun selesai';
        ($t || ($d['keluar'] ?? false)) || $kurang[] = 'B1 Tahapan';
        if (in_array($t, ['KONSTRUKSI', 'BEROPERASI_SEBAGIAN', 'KUMULATIF_SELESAI'], true)) {
            $tiga = ['target_akhir', 'target_kumulatif', 'realisasi_kumulatif'];
            $empat = ['satuan', ...$tiga];
            if ($jenis === 'FISIK') {
                ($terisi('PROGRES_FISIK', $tiga) || $terisi('OUTPUT_UTAMA', $empat)) || $kurang[] = 'B2 Progres fisik/output';
            } else {
                $terisi('OUTPUT_UTAMA', $empat) || $kurang[] = 'B2 Progres fisik/output';
            }
            $terisi('PEMBIAYAAN', $tiga) || $kurang[] = 'B2 Pembiayaan';
        }
        if (in_array($t, ['PENYIAPAN', 'TRANSAKSI'], true)
            && ! collect($e?->milestone ?? [])->contains(fn ($m) => filled($m->status) && array_key_exists($m->kode, config("psn_dashboard.evaluasi_status.milestone.{$jenis}")))) {
            $kurang[] = 'B3 Milestone';
        }
        if (in_array($t, ['BEROPERASI_SEBAGIAN', 'KUMULATIF_SELESAI'], true) && blank($e?->b4_manfaat)) {
            $kurang[] = 'B4 Manfaat';
        }
        if ($berjalan && ! $e?->c1_tipologi && blank($e?->c1_lainnya) && ! $tanpaIsu) {
            $kurang[] = 'C1 Tipologi isu';
        }
        if (($berjalan || $e?->c1_tipologi) && ! $tanpaIsu
            && ! collect($isuEfektif)->contains(fn ($i) => filled($i['uraian']) && filled($i['dukungan']))) {
            $kurang[] = 'C2 Isu dan dukungan yang dibutuhkan';
        }
        $d1 = self::d1($e, $d);
        $d1 || $kurang[] = 'D1 Kebutuhan status PSN';
        if ($d1 === 'MASIH') {
            $e?->d2_fasilitas || $kurang[] = 'D2 Fasilitas';
            if ($berjalan && ! ($e?->d3_tahapan && $e?->d3_target && $e?->d3_tahun_selesai)) {
                $kurang[] = 'D3 Target selanjutnya';
            }
        }

        // Indikator.
        $a = config('psn_dashboard.evaluasi_status.ambang');
        $pf = $capaian['PROGRES_FISIK'];
        $deviasi = $pf['target_kumulatif'] !== null && $pf['realisasi_kumulatif'] !== null ? round($pf['realisasi_kumulatif'] - $pf['target_kumulatif'], 1) : null;
        $pb = $capaian['PEMBIAYAAN'];
        $serapan = $pb['target_kumulatif'] ? round(100 * ($pb['realisasi_kumulatif'] ?? 0) / $pb['target_kumulatif'], 1) : null;
        $jalurKritis = collect($isuEfektif)->where('jalur_kritis', true)->count();
        $mundur = $e?->d3_tahun_selesai && $d['tahun_selesai'] ? $e->d3_tahun_selesai - $d['tahun_selesai'] : null;
        $umur = self::a6($e, $d) ? $this->tahun() - self::a6($e, $d) : null;
        $indikator = ['deviasi_fisik' => $deviasi, 'serapan' => $serapan, 'mundur_tahun' => $mundur, 'umur_psn' => $umur,
            'isu_terbuka' => count($isuEfektif), 'isu_jalur_kritis' => $jalurKritis, 'regulasi_belum' => $d['regulasi_belum']];

        // Rekomendasi sistem dan sinyal pendukungnya.
        $sinyal = [];
        $deviasi !== null && $deviasi <= -$a['deviasi_fisik_poin'] && $sinyal[] = "Progres fisik tertinggal {$this->angka(-$deviasi)} poin dari rencana";
        $serapan !== null && $serapan < $a['serapan_min_persen'] && $sinyal[] = "Serapan anggaran {$this->angka($serapan)}% dari pagu";
        $mundur !== null && $mundur >= $a['mundur_tahun'] && $sinyal[] = "Proyeksi selesai mundur {$mundur} tahun dari target";
        $umur !== null && in_array($t, ['PENYIAPAN', 'TRANSAKSI'], true) && $umur >= $a['penyiapan_maks_tahun']
            && $sinyal[] = 'Masih tahap '.mb_strtolower(config("psn_dashboard.evaluasi_status.tahapan.{$t}"))." {$umur} tahun sejak ditetapkan";
        $jalurKritis && $sinyal[] = "{$jalurKritis} isu terbuka menghambat jalur kritis";
        $d1 === 'MASIH' && ! $e?->d2_fasilitas && $sinyal[] = 'Membutuhkan status PSN tanpa fasilitas yang dipilih';

        $rekomendasi = match (true) {
            $d1 === 'TIDAK', $d['keluar'] ?? false => 'DIKELUARKAN',
            $t === 'KUMULATIF_SELESAI' && $d1 !== 'MASIH' => 'DIKELUARKAN',
            $kurang !== [] => 'BELUM_CUKUP_DATA',
            $sinyal !== [] => 'PERLU_PENDALAMAN',
            default => 'DIPERTAHANKAN',
        };
        if ($d['keluar'] ?? false) {
            array_unshift($sinyal, 'Status PSN di SIRENDA: Keluar dari PSN');
        } elseif ($d1 === 'TIDAK') {
            array_unshift($sinyal, ($e?->d1_kebutuhan ? 'PJ menyatakan' : 'Isian Kebutuhan Status PSN di SIRENDA menyatakan').' tidak lagi membutuhkan status PSN'
                .($e?->d1_alasan ? ': '.config("psn_dashboard.evaluasi_status.d1_alasan.{$e->d1_alasan}") : ''));
        } elseif ($t === 'KUMULATIF_SELESAI') {
            array_unshift($sinyal, 'Tahapan kumulatif selesai');
        }

        return ['data' => $d, 'kurang' => $kurang, 'lengkap' => $kurang === [], 'indikator' => $indikator, 'rekomendasi' => $rekomendasi, 'sinyal' => $sinyal,
            'capaian' => $capaian, 'isu' => $isuEfektif, 'jenis' => $jenis];
    }

    /**
     * Nilai efektif B2 per komponen: isian kertas kerja; bila kosong, data profil (snapshot cut-off).
     *
     * @return array<string, array{satuan:?string, target_akhir:?float, target_kumulatif:?float, realisasi_kumulatif:?float, keterangan:?string, dari_profil:string[]}>
     */
    public function capaian(array $d, ?EvaluasiPsn $e): array
    {
        $miliar = fn ($v) => $v === null ? null : round($v / 1e9, 2);
        $profil = [
            'PROGRES_FISIK' => ['satuan' => '%', 'target_akhir' => $d['progres_rencana'] !== null ? 100.0 : null, 'target_kumulatif' => $d['progres_rencana'], 'realisasi_kumulatif' => $d['progres_realisasi']],
            'PEMBIAYAAN' => ['satuan' => 'Rp miliar', 'target_akhir' => $miliar($d['nilai_investasi_rp'] ?? null), 'target_kumulatif' => $miliar($d['pagu_rp']), 'realisasi_kumulatif' => $miliar($d['realisasi_anggaran_rp'])],
            'REGULASI' => ['satuan' => 'dokumen', 'target_akhir' => ($d['regulasi_total'] ?? 0) ?: null, 'target_kumulatif' => null, 'realisasi_kumulatif' => ($d['regulasi_total'] ?? 0) ? (float) $d['regulasi_ditetapkan'] : null],
        ];
        $isian = collect($e?->capaian ?? [])->keyBy('komponen');
        $hasil = [];
        foreach (config('psn_dashboard.evaluasi_status.capaian') as $k => [, $satuanTetap]) {
            $baris = ['keterangan' => $isian[$k]->keterangan ?? null, 'dari_profil' => []];
            foreach (['satuan', 'target_akhir', 'target_kumulatif', 'realisasi_kumulatif'] as $kol) {
                $v = $isian[$k]->{$kol} ?? null;
                if (blank($v) && isset($profil[$k][$kol])) {
                    $v = $profil[$k][$kol];
                    $baris['dari_profil'][] = $kol;
                }
                $baris[$kol] = $v;
            }
            $baris['satuan'] ??= $satuanTetap;
            $hasil[$k] = $baris;
        }

        return $hasil;
    }

    /** Isu C2: isian kertas kerja; bila belum ada, isu terbuka di profil (jalur kritis dari KP/RO). */
    public function isuEfektif(array $d, ?EvaluasiPsn $e): array
    {
        if ($e && $e->isu->isNotEmpty()) {
            return $e->isu->map(fn ($i) => ['uraian' => $i->uraian, 'tipologi' => $i->tipologi, 'jalur_kritis' => $i->jalur_kritis, 'upaya' => $i->upaya,
                'dukungan' => $i->dukungan, 'dari_profil' => false])->all();
        }

        return $d['isu_profil'] ?? [];
    }

    /** Simpan isian evaluasi; status akhir dihitung ulang dan setiap perubahannya dicatat di riwayat. */
    public function simpan(EvaluasiPsn $e, array $isian, ?string $keterangan = null, ?array $rincian = null): EvaluasiPsn
    {
        return DB::transaction(function () use ($e, $isian, $keterangan, $rincian) {
            $e->fill($isian);
            if ($e->isDirty('hasil_pengecekan') && ! array_key_exists('tindak_lanjut', $isian)) {
                $e->tindak_lanjut = config("psn_dashboard.evaluasi_status.tindak_lanjut_otomatis.{$e->hasil_pengecekan}");
            }
            $lama = $e->exists ? $e->getOriginal('status_akhir') : null;
            $e->status_akhir = $e->hitungStatusAkhir();
            $e->save();
            if ($rincian !== null) {
                $this->simpanRincian($e, $rincian);
            }
            if ($lama !== $e->status_akhir) {
                $e->riwayat()->create(['tanggal' => now()->toDateString(), 'status_akhir' => $e->status_akhir,
                    'keterangan' => $keterangan ?? ($lama ? null : 'Form diterima'), 'user_id' => Auth::id()]);
            }

            return $e;
        });
    }

    /**
     * Rincian kertas kerja (tabel anak). Baris dicocokkan menurut posisi agar ID stabil; baris kosong dibuang,
     * capaian yang seluruh kolomnya kosong dihapus (kembali memakai data profil).
     */
    protected function simpanRincian(EvaluasiPsn $e, array $r): void
    {
        $kosong = fn (array $b, array $kol) => collect($kol)->every(fn ($k) => blank($b[$k] ?? null));
        $kolCap = ['satuan', 'target_akhir', 'target_kumulatif', 'realisasi_kumulatif', 'keterangan'];
        $ada = $e->capaian()->get()->keyBy('komponen');
        // Hanya komponen yang dikirim yang diproses (formulir selalu mengirim semua komponen).
        foreach (array_intersect(array_keys(config('psn_dashboard.evaluasi_status.capaian')), array_keys($r['capaian'] ?? [])) as $k) {
            $b = $r['capaian'][$k] ?? [];
            if ($kosong($b, $kolCap)) {
                $ada->get($k)?->delete();

                continue;
            }
            $nilai = collect($kolCap)->mapWithKeys(fn ($c) => [$c => blank($b[$c] ?? null) ? null : $b[$c]])->all();
            $ada->has($k) ? $ada->get($k)->update($nilai) : $e->capaian()->create(['komponen' => $k] + $nilai);
        }

        $sinkron = function (string $relasi, array $baris, array $kolom, array $wajib) use ($e, $kosong) {
            $baris = array_values(array_filter($baris, fn ($b) => ! $kosong($b, $wajib)));
            $lama = $e->{$relasi}()->get()->values();
            foreach ($baris as $i => $b) {
                $nilai = collect($kolom)->mapWithKeys(fn ($c) => [$c => blank($b[$c] ?? null) ? null : $b[$c]])->all() + ['urutan' => $i + 1];
                isset($lama[$i]) ? $lama[$i]->update($nilai) : $e->{$relasi}()->create($nilai);
            }
            $lama->slice(count($baris))->each->delete();
        };
        // Kelompok yang tidak dikirim tidak disentuh.
        isset($r['milestone']) && $sinkron('milestone', array_map(fn ($m) => $m + ['status' => null], $r['milestone']), ['kode', 'nama_lain', 'status', 'tanggal', 'keterangan'], ['status', 'tanggal', 'keterangan', 'nama_lain']);
        isset($r['isu']) && $sinkron('isu', array_map(fn ($i) => array_merge($i, ['jalur_kritis' => ($i['jalur_kritis'] ?? '') === '' ? null : (bool) $i['jalur_kritis']]), $r['isu']),
            ['uraian', 'tipologi', 'jalur_kritis', 'upaya', 'dukungan'], ['uraian']);
        isset($r['lampiran']) && $sinkron('lampiran', $r['lampiran'], ['jenis', 'judul', 'bentuk', 'tautan'], ['judul']);
        $e->unsetRelation('capaian')->unsetRelation('milestone')->unsetRelation('isu')->unsetRelation('lampiran');
    }

    /** D1 efektif: isian Evaluasi, atau golongan teks "Kebutuhan Status PSN" di SIRENDA bila isian kosong. */
    public static function d1(?EvaluasiPsn $e, array $data): ?string
    {
        return $e?->d1_kebutuhan ?: ($data['d1_profil'] ?? null);
    }

    /** A6 efektif: isian Evaluasi, atau tahun penetapan (tahun_terbit) dari profil SIRENDA bila isian kosong. */
    public static function a6(?EvaluasiPsn $e, array $data): ?int
    {
        return $e?->a6_tahun_penetapan ?: ($data['tahun_terbit'] ?? null);
    }

    /** Ringkasan untuk visual alur (gaya "PSN dalam daftar" RKP). */
    public function alur(Collection $daftar): array
    {
        $per = $daftar->countBy('status_akhir');
        $nama = fn (string $s) => $daftar->where('status_akhir', $s)->sortBy('nama')->take(5)->map(fn ($r) => ['id' => $r['psn_id'], 'nama' => $r['nama']])->values()->all();
        $baru = DB::table('usulan_rekomendasi as r')->join('usulan_psn as u', 'u.id', '=', 'r.usulan_id')
            ->where('r.status', 'FINAL')->where('r.rekomendasi', 'DIREKOMENDASIKAN')->whereNull('r.deleted_at')->whereNull('u.deleted_at')
            ->where('u.tahun_rkp', $this->tahun())->orderBy('u.nama')->get(['u.id', 'u.nama']);
        $dikeluarkan = $per['DIKELUARKAN'] ?? 0;
        $dikeluarkanTotal = $daftar->filter(fn ($r) => $r['status_akhir'] === 'DIKELUARKAN' || ($r['data']['keluar'] ?? false))->count();

        return [
            'total' => $daftar->count(),
            'keluar' => $daftar->where('data.keluar', true)->count(),
            'sumber' => $daftar->countBy(fn ($r) => $r['sumber'] ?: 'LAINNYA')->sortKeys()->all(),
            // Progres Form Pengendalian per sumber PSN (RKP / Permenko): diterima vs belum kembali.
            'form_sumber' => $daftar->groupBy(fn ($r) => $r['sumber'] ?: 'LAINNYA')->sortKeys()
                ->map(fn ($g) => ['diterima' => $g->where('status_akhir', '<>', 'BELUM_DITERIMA')->count(), 'belum' => $g->where('status_akhir', 'BELUM_DITERIMA')->count()])->all(),
            'diterima' => $daftar->count() - ($per['BELUM_DITERIMA'] ?? 0),
            'lengkap' => $daftar->where('lengkap', true)->count(),
            'status' => collect(config('psn_dashboard.evaluasi_status.status'))->map(fn ($l, $k) => $per[$k] ?? 0)->all(),
            'dipertahankan_nama' => $nama('DIPERTAHANKAN'),
            'dikeluarkan_nama' => $nama('DIKELUARKAN'),
            'baru' => $baru->count(),
            'baru_nama' => $baru->take(5)->map(fn ($u) => ['id' => $u->id, 'nama' => $u->nama])->all(),
            // Proyeksi daftar RKP berikutnya = PSN tahun berjalan + usulan baru ditetapkan - PSN tahun berjalan yang dikeluarkan.
            // Dikeluarkan = indikasi status Dikeluarkan, atau status "Keluar dari PSN" di SIRENDA.
            'proyeksi' => $daftar->count() + $baru->count() - $dikeluarkanTotal,
            'dikeluarkan_total' => $dikeluarkanTotal,
            'dikeluarkan_keluar' => $daftar->filter(fn ($r) => $r['data']['keluar'] && $r['status_akhir'] !== 'DIKELUARKAN')->count(),
            'dikeluarkan_aktif' => $daftar->filter(fn ($r) => ! $r['data']['keluar'] && $r['status_akhir'] === 'DIKELUARKAN')->count(),
            'rekomendasi' => collect(config('psn_dashboard.evaluasi_status.rekomendasi'))->map(fn ($l, $k) => $daftar->where('rekomendasi', $k)->count())->all(),
        ];
    }

    /**
     * Jumlah PSN per status akhir per hari (status pada suatu hari = entri riwayat terakhir PSN dengan tanggal <= hari itu;
     * PSN belum dihitung sebelum entri pertamanya).
     */
    public function harian(Collection $daftar, ?CarbonImmutable $akhir = null): array
    {
        $hari = (int) config('psn_dashboard.evaluasi_status.hari_grafik');
        $akhir ??= CarbonImmutable::today();
        $awal = $akhir->subDays($hari - 1);
        $evaluasiId = $daftar->pluck('evaluasi')->filter()->pluck('id')->all();
        $riwayat = DB::table('evaluasi_psn_riwayat')->whereIn('evaluasi_psn_id', $evaluasiId ?: [0])->where('tanggal', '<=', $akhir->toDateString())
            ->orderBy('tanggal')->orderBy('id')->get(['evaluasi_psn_id', 'tanggal', 'status_akhir'])->groupBy('evaluasi_psn_id');

        $status = array_keys(array_diff_key(config('psn_dashboard.evaluasi_status.status'), ['BELUM_DITERIMA' => 1]));
        $hasil = [];
        for ($t = $awal; $t <= $akhir; $t = $t->addDay()) {
            $tgl = $t->toDateString();
            $n = array_fill_keys($status, 0);
            foreach ($riwayat as $baris) {
                $s = $baris->filter(fn ($r) => $r->tanggal <= $tgl)->last()?->status_akhir;
                $s && isset($n[$s]) && $n[$s]++;
            }
            $hasil[] = ['tanggal' => $tgl, 'status' => $n];
        }

        return $hasil;
    }

    protected function angka(float $v): string
    {
        return number_format($v, $v == (int) $v ? 0 : 1, ',', '.');
    }
}
