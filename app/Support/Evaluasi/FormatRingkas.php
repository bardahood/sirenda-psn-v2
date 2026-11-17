<?php

namespace App\Support\Evaluasi;

use App\Models\Psn;
use App\Services\EvaluasiStatusService;
use Illuminate\Support\Collection;

/**
 * Kolom kertas kerja pengendalian, identik dengan sheet "Format Ringkas" (kertas kerja Input Form Pengendalian PSN).
 * Dipakai halaman Kertas Kerja dan unduhan Excel. Setiap sel ditandai asal nilainya:
 * PROFIL (data SIRENDA), ISIAN (diisi di evaluasi), atau OTOMATIS (hasil hitung).
 */
class FormatRingkas
{
    /** @var array<int, array{grup:string, label:string, kunci:string, wajib:bool}> */
    protected array $kolom = [];

    public function __construct()
    {
        $c = config('psn_dashboard.evaluasi_status');
        $tw = 'TW';
        $tambah = function (string $grup, array $isi) {
            foreach ($isi as [$kunci, $label, $wajib]) {
                $this->kolom[] = ['grup' => $grup, 'label' => $label, 'kunci' => $kunci, 'wajib' => $wajib];
            }
        };
        $tambah('Identitas', [['no', 'No', false], ['kode', 'Kode PSN', true]]);
        foreach ([['a1', 'A1', 'Nama PSN'], ['a2', 'A2', 'Klaster PSN'], ['a3', 'A3', 'Lokasi'], ['a4', 'A4', 'Penanggung Jawab, Pengelola, dan Pelaksana'],
            ['a5', 'A5', 'Dasar penetapan PSN / regulasi pendukung'], ['a6', 'A6', 'Tahun pertama kali ditetapkan sebagai PSN'], ['a7', 'A7', 'Target tahun selesai'],
            ['a8', 'A8', 'Sumber pendanaan'], ['a9', 'A9', 'Total kebutuhan pendanaan / nilai investasi (Rp)'], ['a10', 'A10', 'Deskripsi ringkas'],
            ['a11', 'A11', 'Output akhir'], ['a12', 'A12', 'Jenis PSN']] as [$k, $g, $l]) {
            $tambah($g, [[$k, $l, in_array($k, ['a6', 'a7'], true)]]);
        }
        $tambah('B1 Tahapan proyek / program', [['b1', 'Tahapan', true]]);
        foreach ([['FISIK', 'B2.a Fisik'], ['NON_FISIK', 'B2.b Non-Fisik']] as [$jenis, $awalan]) {
            foreach ($c['capaian_jenis'][$jenis] as $komp) {
                [$label] = $c['capaian'][$komp];
                $k = strtolower($jenis.'_'.$komp);
                $isi = [];
                if (! in_array($komp, ['PROGRES_FISIK', 'PEMBIAYAAN', 'PERIZINAN', 'REGULASI'], true)) {
                    $isi[] = ["{$k}_satuan", 'Satuan', $komp === 'OUTPUT_UTAMA'];
                }
                $wajib = in_array($komp, ['PROGRES_FISIK', 'OUTPUT_UTAMA', 'PEMBIAYAAN'], true);
                $isi[] = ["{$k}_target_akhir", 'Target akhir'.($komp === 'PEMBIAYAAN' ? ' (Rp miliar)' : ($komp === 'PROGRES_FISIK' ? ' (%)' : '')), $wajib];
                $isi[] = ["{$k}_target_kumulatif", "Target kumulatif s.d. {$tw}", $wajib];
                $isi[] = ["{$k}_realisasi_kumulatif", "Realisasi kumulatif s.d. {$tw}", $wajib];
                $isi[] = ["{$k}_keterangan", 'Keterangan / sumber data', false];
                $tambah("{$awalan} – {$label}", $isi);
            }
        }
        $tambah('B3.a Milestone Pra-Konstruksi (PSN Fisik)', [['b3a_jenis', 'Jenis Milestone', true], ['b3a_status', 'Realisasi', true], ['b3a_tanggal', 'Tanggal selesai / target', false], ['b3a_ket', 'Keterangan', false]]);
        $tambah('B3.b Milestone Persiapan Program (PSN Non-Fisik)', [['b3b_jenis', 'Jenis Milestone', true], ['b3b_status', 'Realisasi', true], ['b3b_tanggal', 'Tanggal selesai / target', false], ['b3b_ket', 'Keterangan', false]]);
        $tambah('B4 Manfaat proyek / program', [['b4', 'Manfaat yang sudah terwujud', true]]);
        $tambah('C1 Tipologi isu', [['c1', 'Tipologi isu (1 opsi per baris)', true]]);
        $tambah('C2 Uraian isu', [['c2_uraian', 'Uraian isu / permasalahan', true], ['c2_dukungan', 'Dukungan yang dibutuhkan', true]]);
        $tambah('D1 Kebutuhan status PSN pada RKP '.$c['tahun_rkp'], [['d1', 'Kebutuhan status PSN', true], ['d1_alasan', 'Alasan (jika tidak lagi membutuhkan)', false], ['d1_alasan_lain', 'Alasan lainnya', false]]);
        $tambah('D2 Fasilitas kemudahan (PP 42/2021)', [['d2', 'Fasilitas yang dipilih (1 opsi per baris)', true], ['d2_justifikasi', 'Justifikasi singkat', false]]);
        $tambah('D3 Target selanjutnya', [['d3_tahapan', 'Tahapan target akhir '.$c['tahun_rkp'], true], ['d3_target', 'Target progres / output akhir '.$c['tahun_rkp'], true], ['d3_tahun', 'Target tahun selesai (proyeksi)', true]]);
        $tambah('PIC / Pejabat Penanggung Jawab (min. Eselon II)', [['pic_nama', 'Nama', false], ['pic_jabatan', 'Jabatan', false], ['pic_unit', 'Unit / Instansi', false], ['pic_kontak', 'Email / HP', false], ['pic_tanggal', 'Tanggal', false]]);
        $tambah('Daftar lampiran (1 lampiran per baris)', [['lamp_jenis', 'Jenis lampiran', false], ['lamp_judul', 'Judul / uraian singkat', false], ['lamp_bentuk', 'Bentuk', false], ['lamp_tautan', 'Tautan / nama file', false]]);
        $tambah('Informasi tambahan', [['info', 'Uraian bebas', false]]);
        $tambah('Cek otomatis', [['cek_kurang', 'Isian wajib yang belum terisi', false], ['cek_status', 'Status kelengkapan', false]]);
        $tambah('Hasil Pengecekan', [['hasil', 'Hasil Pengecekan', false]]);
        $tambah('Tindak Lanjut', [['tl', 'Tindak Lanjut', false]]);
        $tambah('Catatan Hasil Analisis', [['catatan', 'Catatan Hasil Analisis', false]]);
        $tambah('Indikasi Status PSN', [['indikasi', 'Indikasi Status PSN', false]]);
        $tambah('Cek otomatis ', [['status_akhir', 'Status Akhir', false]]);
    }

    public function kolom(): array
    {
        return $this->kolom;
    }

    /** Kelompok kolom berurutan: [[grup, jumlah kolom], ...] untuk header bertingkat. */
    public function grup(): array
    {
        $hasil = [];
        foreach ($this->kolom as $k) {
            if ($hasil && $hasil[count($hasil) - 1][0] === $k['grup']) {
                $hasil[count($hasil) - 1][1]++;
            } else {
                $hasil[] = [$k['grup'], 1];
            }
        }

        return $hasil;
    }

    /** Data A1-A11 profil untuk banyak PSN sekaligus. @return array<int, array> */
    public function profilA(array $psnId): array
    {
        $hasil = [];
        foreach (Psn::whereIn('id', $psnId ?: [0])->with(['klaster', 'lokasi.provinsi', 'lokasi.kabupaten', 'sumberDana.sumberDana', 'kelembagaan.penanggungJawab', 'kelembagaan.instansi'])->get() as $p) {
            $peran = fn (string $r) => $p->kelembagaan->where('peran', $r)->sortBy('urutan')
                ->map(fn ($k) => $k->penanggungJawab?->nama ?? $k->instansi?->nama ?? $k->nama_teks)->filter()->unique()->implode(', ');
            $hasil[$p->id] = [
                'a3' => $p->lokasi->map(fn ($l) => $l->provinsi_kode === '00' ? 'Nasional' : trim(($l->provinsi?->nama ?? $l->provinsi_kode).($l->kabupaten ? ' ('.$l->kabupaten->nama.')' : '')))->unique()->implode('; '),
                'a4' => collect(['PJ' => $peran('PENANGGUNG_JAWAB'), 'Pengelola' => $peran('PENGELOLA'), 'Pelaksana' => $peran('PELAKSANA')])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode('; '),
                'a5' => $p->dasar_penetapan,
                'a8' => $this->sumberPendanaan($p->sumberDana->map(fn ($d) => $d->sumberDana?->kode)->filter()->all()),
                'a9' => $p->nilai_investasi_rp !== null ? (float) $p->nilai_investasi_rp : null,
                'a10' => $p->deskripsi,
                'a11' => $p->output,
            ];
        }

        return $hasil;
    }

    /**
     * Nilai satu baris (hasil EvaluasiStatusService::daftar()) beserta asal nilainya.
     *
     * @return array{nilai: array<string, mixed>, asal: array<string, string>}
     */
    public function baris(array $r, array $profilA, int $no): array
    {
        $c = config('psn_dashboard.evaluasi_status');
        $e = $r['evaluasi'];
        $d = $r['data'];
        $n = [];
        $asal = [];
        $set = function (string $k, $v, string $sumber = 'ISIAN') use (&$n, &$asal) {
            $n[$k] = $v === '' ? null : $v;
            $asal[$k] = $sumber;
        };
        $daftar = fn (array $baris) => $baris ? implode("\n", array_map(fn ($v, $i) => ($i + 1).'. '.$v, $baris, array_keys($baris))) : null;
        $label = fn (string $g, $k) => $k ? ($c[$g][$k] ?? $k) : null;

        $set('no', $no, 'OTOMATIS');
        $set('kode', $r['kode'], 'PROFIL');
        $set('a1', $r['nama'], 'PROFIL');
        $set('a2', $r['klaster'], 'PROFIL');
        $set('a8', $label('sumber_pendanaan', $e?->a8_sumber_pendanaan ?: ($profilA['a8'] ?? null)), $e?->a8_sumber_pendanaan ? 'ISIAN' : 'PROFIL');
        foreach (['a3', 'a4', 'a5', 'a9', 'a10', 'a11'] as $k) {
            $set($k, $profilA[$k] ?? null, 'PROFIL');
        }
        $set('a6', EvaluasiStatusService::a6($e, $d), $e?->a6_tahun_penetapan ? 'ISIAN' : 'PROFIL');
        $set('a7', $d['tahun_selesai'], 'PROFIL');
        $set('a12', $label('jenis_psn', $e?->jenis_psn));
        $set('b1', ($d['keluar'] ?? false) ? 'Keluar dari PSN' : $label('tahapan', $d['tahapan']), 'PROFIL');

        foreach (['FISIK', 'NON_FISIK'] as $jenis) {
            $aktif = $r['jenis'] === $jenis;
            foreach ($c['capaian_jenis'][$jenis] as $komp) {
                $k = strtolower($jenis.'_'.$komp);
                $cap = $r['capaian'][$komp];
                foreach (['satuan', 'target_akhir', 'target_kumulatif', 'realisasi_kumulatif', 'keterangan'] as $kol) {
                    if (! array_key_exists("{$k}_{$kol}", array_flip(array_column($this->kolom, 'kunci')))) {
                        continue;
                    }
                    $set("{$k}_{$kol}", $aktif ? $cap[$kol] : null, in_array($kol, $cap['dari_profil'], true) ? 'PROFIL' : 'ISIAN');
                }
            }
        }

        foreach (['FISIK' => 'b3a', 'NON_FISIK' => 'b3b'] as $jenis => $k) {
            $urut = array_keys($c['milestone'][$jenis]);
            $ms = collect($e?->milestone ?? [])->filter(fn ($m) => in_array($m->kode, $urut, true))->sortBy(fn ($m) => array_search($m->kode, $urut))->values();
            $nomor = fn ($m) => (array_search($m->kode, $urut) + 1).'. ';
            $set("{$k}_jenis", $ms->map(fn ($m) => $nomor($m).($m->nama_lain ?: $c['milestone'][$jenis][$m->kode]))->implode("\n") ?: null);
            $set("{$k}_status", $ms->map(fn ($m) => $nomor($m).($c['status_milestone'][$m->status] ?? '–'))->implode("\n") ?: null);
            $set("{$k}_tanggal", $ms->map(fn ($m) => $nomor($m).($m->tanggal?->format('d-m-Y') ?? '–'))->implode("\n") ?: null);
            $set("{$k}_ket", $ms->map(fn ($m) => $nomor($m).($m->keterangan ?: '–'))->implode("\n") ?: null);
        }
        $set('b4', $e?->b4_manfaat);
        $tipologi = collect($e?->c1_tipologi ?? [])->map(fn ($k) => "{$k}. ".($c['tipologi_isu'][$k] ?? $k))->all();
        if (filled($e?->c1_lainnya)) {
            $tipologi[] = '11. Lainnya: '.$e->c1_lainnya;
        }
        $set('c1', $tipologi ? implode("\n", $tipologi) : ($e?->tanpa_isu ? '11. Lainnya: Tidak ada isu' : null));
        $isuDariProfil = (bool) ($r['isu'][0]['dari_profil'] ?? false);
        $set('c2_uraian', $daftar(array_map(fn ($i) => ($i['tipologi'] || $i['jalur_kritis'] !== null
            ? '['.implode('; ', array_filter([$i['tipologi'] ? "Tipologi {$i['tipologi']}" : null, $i['jalur_kritis'] === null ? null : ($i['jalur_kritis'] ? 'menghambat jalur kritis' : 'tidak menghambat jalur kritis')])).'] '
            : '').$i['uraian'].(filled($i['upaya']) ? ' – Upaya: '.$i['upaya'] : ''), $r['isu'])), $isuDariProfil ? 'PROFIL' : 'ISIAN');
        $set('c2_dukungan', $daftar(array_map(fn ($i) => $i['dukungan'] ?: '–', $r['isu'])), $isuDariProfil ? 'PROFIL' : 'ISIAN');
        $set('d1', $label('d1', EvaluasiStatusService::d1($e, $d)), $e?->d1_kebutuhan ? 'ISIAN' : (($d['d1_profil'] ?? null) ? 'PROFIL' : 'ISIAN'));
        $set('d1_alasan', $label('d1_alasan', $e?->d1_alasan));
        $set('d1_alasan_lain', $e?->d1_alasan_lain);
        $set('d2', collect($e?->d2_fasilitas ?? [])->map(fn ($k) => (array_search($k, array_keys($c['d2_fasilitas'])) + 1).'. '.($c['d2_fasilitas'][$k] ?? $k))->implode("\n") ?: null);
        $set('d2_justifikasi', $e?->d2_justifikasi);
        $set('d3_tahapan', $label('tahapan', $e?->d3_tahapan));
        $set('d3_target', $e?->d3_target);
        $set('d3_tahun', $e?->d3_tahun_selesai);
        foreach (['nama', 'jabatan', 'unit', 'kontak', 'tanggal'] as $k) {
            $set("pic_{$k}", $e?->pic[$k] ?? null);
        }
        $lamp = collect($e?->lampiran ?? [])->values();
        $set('lamp_jenis', $daftar($lamp->map(fn ($l) => $label('lampiran_jenis', $l->jenis) ?? '–')->all()));
        $set('lamp_judul', $daftar($lamp->pluck('judul')->all()));
        $set('lamp_bentuk', $daftar($lamp->map(fn ($l) => $label('lampiran_bentuk', $l->bentuk) ?? '–')->all()));
        $set('lamp_tautan', $daftar($lamp->map(fn ($l) => $l->tautan ?: '–')->all()));
        $set('info', $e?->info_tambahan);
        $set('cek_kurang', $r['kurang'] ? implode('; ', $r['kurang']) : null, 'OTOMATIS');
        $set('cek_status', $r['lengkap'] ? 'Lengkap' : 'Belum Lengkap', 'OTOMATIS');
        $set('hasil', $label('hasil_pengecekan', $e?->hasil_pengecekan));
        $set('tl', $label('tindak_lanjut', $e?->tindak_lanjut));
        $set('catatan', $e?->catatan_analisis);
        $set('indikasi', $label('indikasi', $e?->indikasi_status));
        $set('status_akhir', $c['status'][$r['status_akhir']], 'OTOMATIS');

        return ['nilai' => $n, 'asal' => $asal];
    }

    /**
     * Sel yang dapat diisi langsung di kertas kerja: kunci => [tipe, izin, opsi]. Tipe: angka, teks, panjang, tanggal,
     * pilihan, multi (kotak centang), rincian (diisi lewat formulir per PSN). Kolom profil (A1-A5, A7-A11, B1) tidak dapat diubah
     * di sini karena sumbernya SIRENDA PEKS3; kolom B2 yang berasal dari profil dapat ditimpa dengan angka Form Pengendalian.
     */
    public function editor(): array
    {
        $c = config('psn_dashboard.evaluasi_status');
        $ed = [
            'a6' => ['angka', 'input'], 'a8' => ['pilihan', 'input', $c['sumber_pendanaan']], 'a12' => ['pilihan', 'input', $c['jenis_psn']],
            'b4' => ['panjang', 'input'], 'c1' => ['multi', 'input', array_diff_key($c['tipologi_isu'], ['11' => 1])],
            'd1' => ['pilihan', 'input', $c['d1']], 'd1_alasan' => ['pilihan', 'input', $c['d1_alasan']], 'd1_alasan_lain' => ['teks', 'input'],
            'd2' => ['multi', 'input', $c['d2_fasilitas']], 'd2_justifikasi' => ['panjang', 'input'],
            'd3_tahapan' => ['pilihan', 'input', $c['tahapan']], 'd3_target' => ['teks', 'input'], 'd3_tahun' => ['angka', 'input'],
            'pic_nama' => ['teks', 'input'], 'pic_jabatan' => ['teks', 'input'], 'pic_unit' => ['teks', 'input'], 'pic_kontak' => ['teks', 'input'], 'pic_tanggal' => ['tanggal', 'input'],
            'info' => ['panjang', 'input'],
            'hasil' => ['pilihan', 'verifikasi', $c['hasil_pengecekan']], 'tl' => ['pilihan', 'verifikasi', $c['tindak_lanjut']], 'catatan' => ['panjang', 'verifikasi'],
            'indikasi' => ['pilihan', 'kelola', $c['indikasi']],
        ];
        foreach (['b3a_jenis', 'b3a_status', 'b3a_tanggal', 'b3a_ket', 'b3b_jenis', 'b3b_status', 'b3b_tanggal', 'b3b_ket'] as $k) {
            $ed[$k] = ['rincian', 'input', 'b3'];
        }
        foreach (['c2_uraian', 'c2_dukungan'] as $k) {
            $ed[$k] = ['rincian', 'input', 'c2'];
        }
        foreach (['lamp_jenis', 'lamp_judul', 'lamp_bentuk', 'lamp_tautan'] as $k) {
            $ed[$k] = ['rincian', 'input', 'lampiran'];
        }
        foreach (['FISIK', 'NON_FISIK'] as $jenis) {
            foreach ($c['capaian_jenis'][$jenis] as $komp) {
                foreach (['satuan', 'target_akhir', 'target_kumulatif', 'realisasi_kumulatif', 'keterangan'] as $kol) {
                    $k = strtolower("{$jenis}_{$komp}_{$kol}");
                    if (in_array($k, array_column($this->kolom, 'kunci'), true)) {
                        $ed[$k] = [in_array($kol, ['satuan', 'keterangan'], true) ? 'teks' : 'angka', 'input', null, $jenis, $komp, $kol];
                    }
                }
            }
        }

        return array_map(fn ($e) => ['tipe' => $e[0], 'izin' => $e[1], 'opsi' => $e[2] ?? null, 'jenis' => $e[3] ?? null, 'komponen' => $e[4] ?? null, 'kolom' => $e[5] ?? null], $ed);
    }

    /** Nilai mentah (kode, angka, tanggal ISO) untuk diedit langsung; label hanya untuk tampilan. */
    public function mentah(array $r, ?string $a8Profil = null): array
    {
        $e = $r['evaluasi'];
        $m = [
            'a6' => EvaluasiStatusService::a6($e, $r['data']), 'a8' => $e?->a8_sumber_pendanaan ?: $a8Profil, 'a12' => $e?->jenis_psn, 'b4' => $e?->b4_manfaat, 'c1' => $e?->c1_tipologi ?? [], 'c1_lainnya' => $e?->c1_lainnya,
            'd1' => EvaluasiStatusService::d1($e, $r['data']), 'd1_alasan' => $e?->d1_alasan, 'd1_alasan_lain' => $e?->d1_alasan_lain, 'd2' => $e?->d2_fasilitas ?? [], 'd2_justifikasi' => $e?->d2_justifikasi,
            'd3_tahapan' => $e?->d3_tahapan, 'd3_target' => $e?->d3_target, 'd3_tahun' => $e?->d3_tahun_selesai, 'info' => $e?->info_tambahan,
            'hasil' => $e?->hasil_pengecekan, 'tl' => $e?->tindak_lanjut, 'catatan' => $e?->catatan_analisis, 'indikasi' => $e?->indikasi_status,
        ];
        foreach (['nama', 'jabatan', 'unit', 'kontak', 'tanggal'] as $k) {
            $m["pic_{$k}"] = $e?->pic[$k] ?? null;
        }
        foreach ($this->editor() as $k => $ed) {
            if ($ed['komponen']) {
                $m[$k] = $r['capaian'][$ed['komponen']][$ed['kolom']] ?? null;
            }
        }

        return $m;
    }

    /** Tampilan nilai sel. */
    public static function tampil(string $k, $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        if ($k === 'a9') {
            return 'Rp '.number_format((float) $v, 0, ',', '.');
        }
        if (preg_match('/_(target_akhir|target_kumulatif|realisasi_kumulatif)$/', $k)) {
            return number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 2, ',', '.');
        }

        return (string) $v;
    }

    public static function angka(string $k): bool
    {
        return (bool) preg_match('/_(target_akhir|target_kumulatif|realisasi_kumulatif)$/', $k) || in_array($k, ['no', 'a9'], true);
    }

    public static function lebar(string $k): string
    {
        return match (true) {
            // Tiga kolom pertama dikunci (sticky) pada posisi kiri 0 / 4.5rem / 13rem: lebarnya harus pasti.
            $k === 'no' => 'w-[4.5rem] min-w-[4.5rem] max-w-[4.5rem]',
            $k === 'kode' => 'w-[8.5rem] min-w-[8.5rem] max-w-[8.5rem] break-words',
            $k === 'a1' => 'w-[16rem] min-w-[16rem] max-w-[16rem]',
            in_array($k, ['a6', 'a7', 'd3_tahun'], true) => 'min-w-[6.5rem]',
            in_array($k, ['a1', 'a4', 'a10', 'c2_uraian', 'c2_dukungan', 'catatan', 'b3a_jenis', 'b3b_jenis', 'b4', 'd2', 'c1', 'cek_kurang'], true) || str_ends_with($k, 'keterangan') => 'min-w-[16rem] max-w-[22rem]',
            default => 'min-w-[8.5rem] max-w-[14rem]',
        };
    }

    /** Kunci kolom yang wajib tetapi masih kosong (dari hasil cek kelengkapan), untuk penandaan sel. */
    public function selKurang(array $r): array
    {
        $j = strtolower($r['jenis']);
        $b2 = fn (string $komp, bool $satuan) => array_merge($satuan ? ["{$j}_{$komp}_satuan"] : [], ["{$j}_{$komp}_target_akhir", "{$j}_{$komp}_target_kumulatif", "{$j}_{$komp}_realisasi_kumulatif"]);
        $peta = [
            'A6' => ['a6'], 'A7' => ['a7'], 'B1' => ['b1'],
            'B2 Progres' => $r['jenis'] === 'FISIK' ? $b2('progres_fisik', false) : $b2('output_utama', true),
            'B2 Pembiayaan' => $b2('pembiayaan', false),
            'B3' => $r['jenis'] === 'FISIK' ? ['b3a_jenis', 'b3a_status'] : ['b3b_jenis', 'b3b_status'],
            'B4' => ['b4'], 'C1' => ['c1'], 'C2' => ['c2_uraian', 'c2_dukungan'], 'D1' => ['d1'], 'D2' => ['d2'], 'D3' => ['d3_tahapan', 'd3_target', 'd3_tahun'],
        ];
        $hasil = [];
        foreach ($r['kurang'] as $k) {
            foreach ($peta as $awal => $kunci) {
                if (str_starts_with($k, $awal)) {
                    array_push($hasil, ...$kunci);
                }
            }
        }

        return array_values(array_unique($hasil));
    }

    /** Semua baris (urutan daftar) siap ditampilkan/diekspor. */
    public function semua(Collection $daftar): Collection
    {
        $profil = $this->profilA($daftar->pluck('psn_id')->all());

        return $daftar->values()->map(fn ($r, $i) => ['r' => $r, 'kurang' => $this->selKurang($r), 'mentah' => $this->mentah($r, $profil[$r['psn_id']]['a8'] ?? null)]
            + $this->baris($r, $profil[$r['psn_id']] ?? [], $i + 1));
    }

    /** Kode sumber dana profil -> kode pilihan A8 (Daftar Pilihan); lebih dari satu kategori = CAMPURAN. */
    public function sumberPendanaan(array $kodeProfil): ?string
    {
        $peta = config('psn_dashboard.evaluasi_status.peta_sumber_dana');
        $kat = array_values(array_unique(array_filter(array_map(fn ($k) => $peta[$k] ?? 'LAINNYA', $kodeProfil))));

        return match (count($kat)) {
            0 => null,
            1 => $kat[0],
            default => 'CAMPURAN',
        };
    }
}
