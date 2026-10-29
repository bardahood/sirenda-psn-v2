<?php

namespace App\Services;

use App\Models\UsulanPsi;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengambil usulan PSN dari API SIF dan memasukkannya lewat jalur impor yang sama dengan kontrak
 * "psi.usulan.v1" (PsiImportService): idempoten per kode_usulan. Versi naik hanya bila isi detail
 * dari SIF berubah (sidik jari sha256), sehingga penilaian FINAL ditandai "perlu tinjau ulang".
 */
class SifSinkronService
{
    public function __construct(protected SifClient $sif, protected PsiImportService $impor) {}

    /** @return array{baru:int, diperbarui:int, tetap:int, gagal:int, galat:string[]} */
    public function sinkron(bool $belumDinilai = false, ?\Closure $log = null): array
    {
        $hasil = ['baru' => 0, 'diperbarui' => 0, 'tetap' => 0, 'gagal' => 0, 'galat' => []];
        $halaman = 1;
        do {
            $daftar = $this->sif->daftar($halaman, $belumDinilai);
            foreach ($daftar['data'] ?? [] as $ringkas) {
                $kode = (string) ($ringkas['kode_usulan'] ?? '');
                try {
                    $status = $this->satu($kode);
                    $hasil[$status]++;
                    $log && $log("{$kode}: {$status}");
                } catch (Throwable $e) {
                    $hasil['gagal']++;
                    $hasil['galat'][] = "{$kode}: {$e->getMessage()}";
                    $this->impor->log('AMBIL_USULAN', $kode ?: null, null, $e instanceof SifException ? $e->getCode() : null, false, 'Gagal mengambil usulan dari SIF: '.$e->getMessage(), null, null, 'KELUAR');
                    $log && $log("{$kode}: GAGAL {$e->getMessage()}");
                }
            }
            $terakhir = (int) ($daftar['meta']['last_page'] ?? 1);
        } while (++$halaman <= $terakhir);

        $this->impor->log('SINKRON', null, null, 200, $hasil['gagal'] === 0,
            sprintf('Sinkron SIF: %d baru, %d diperbarui, %d tetap, %d gagal.', $hasil['baru'], $hasil['diperbarui'], $hasil['tetap'], $hasil['gagal']), null, null, 'KELUAR');

        return $hasil;
    }

    /** Ambil & impor satu usulan; mengembalikan 'baru' | 'diperbarui' | 'tetap'. */
    public function satu(string $kode): string
    {
        $detail = $this->sif->detail($kode);
        // Status SIF (submitted/under_review) bukan isi usulan: perubahannya tidak menaikkan versi.
        $sidik = hash('sha256', json_encode($this->urutkan(Arr::except($detail, ['status']))));
        $psi = UsulanPsi::withTrashed()->where('psi_id', $kode)->first();
        if ($psi && ($psi->payload_terakhir['sif_sidik'] ?? null) === $sidik) {
            return 'tetap';
        }

        $payload = $this->petakan($detail) + ['sif_sidik' => $sidik];
        $payload['versi'] = ($psi?->versi ?? 0) + 1;
        $h = $this->impor->terima($payload);

        return $h['baru'] ? 'baru' : 'diperbarui';
    }

    /** Model data SIF (Dokumen Integrasi §3–4) -> kontrak psi.usulan.v1 (PsiImportService::aturan). */
    public function petakan(array $d): array
    {
        $du = $d['data_umum'] ?? [];
        $ru = $d['rincian_usulan'] ?? [];
        $profil = $ru['profil_proyek'] ?? [];
        $proyek = $d['proyek'] ?? [];
        $pengusul = $d['proposer'] ?? [];
        $kesiapan = $d['kesiapan'] ?? [];
        $submitted = $this->tanggal($d['submitted_at'] ?? null);
        $teks = fn ($v) => is_scalar($v) && trim((string) $v) !== '' ? trim((string) $v) : null;

        return [
            'psi_id' => (string) $d['kode_usulan'],
            'status' => 'DIAJUKAN',
            'diajukan_at' => $submitted?->toIso8601String(),
            'tahun_rkp' => (int) (config('psn_dashboard.psi.sif.tahun_rkp') ?: ($submitted ?? now())->year + 1),
            'surat' => array_filter([
                'psi_surat_id' => (string) $d['kode_usulan'],
                'nomor' => $teks($d['surat']['nomor'] ?? null), 'sifat' => $teks($d['surat']['sifat'] ?? null),
                'tempat' => $teks($d['surat']['tempat'] ?? null), 'tanggal' => $this->tanggal($d['surat']['tanggal'] ?? null)?->toDateString(),
                'ditujukan_kepada' => $teks($d['surat']['ditujukan_kepada'] ?? null),
                'perihal' => 'Usulan Proyek Strategis Nasional',
            ], fn ($v) => $v !== null),
            'pengusul' => [
                'instansi' => Str::limit($teks($du['instansi_pengusul'] ?? null) ?? $teks($pengusul['instansi'] ?? null) ?? '(instansi tidak diisi)', 250, ''),
                'jenis_instansi' => $teks($pengusul['jenis_instansi'] ?? null) ?? $teks($du['jenis_instansi'] ?? null),
                'kontak' => array_filter(['nama' => $teks($pengusul['nama'] ?? null), 'email' => filter_var($pengusul['email'] ?? null, FILTER_VALIDATE_EMAIL) ?: null,
                    'jabatan' => $teks($d['penandatangan']['jabatan'] ?? null)]),
            ],
            'data_umum' => [
                'nama' => Str::limit($teks($du['nama_usulan_psn'] ?? null) ?? $teks($proyek['nama'] ?? null) ?? (string) $d['kode_usulan'], 495, ''),
                'sektor' => $teks($du['sektor'] ?? null),
                'klaster' => ($du['klaster'] ?? null) === 'Lainnya (jelaskan)' || ($du['klaster'] ?? null) === 'Lainnya' ? ($teks($du['klaster_lainnya'] ?? null) ?? 'Lainnya') : $teks($du['klaster'] ?? null),
                'jenis_psn' => $teks($du['jenis_psn'] ?? null),
                'nilai_investasi_rp' => $this->rupiah($d['rincian_tambahan']['capex'] ?? null),
                'lokasi' => array_map(fn ($k) => ['provinsi_kode' => $k], $this->provinsi(implode(' ', array_filter([$teks($profil['lokasi'] ?? null), $teks($proyek['lokasi'] ?? null)])))),
            ],
            'rincian' => [
                'tujuan' => $teks($profil['tujuan'] ?? null) ?? $teks($proyek['tujuan'] ?? null),
                'sasaran' => $teks($profil['sasaran'] ?? null),
                'lingkup_pekerjaan_html' => $this->paragraf($profil['rangkuman_lingkup'] ?? $proyek['lingkup'] ?? null),
                'bagian_lain' => array_values(array_filter([
                    $this->bagianProyekList($d),
                    $this->bagianKriteriaUtama($ru['kriteria_utama'] ?? []),
                    $this->bagianKriteriaPendukung($ru['kriteria_pendukung'] ?? []),
                    $this->bagianKriteriaKesiapan($ru['kriteria_kesiapan'] ?? []),
                    $this->bagianKunciNilai('Rincian Tambahan (CAPEX/OPEX/Pendapatan)', $d['rincian_tambahan'] ?? []),
                    $this->bagianCatatanBappenas($d['catatan_review_bappenas'] ?? []),
                ])),
            ],
            'kesiapan' => [
                'jalur_kritis' => $this->jalurKritis($kesiapan['jalur_kritis'] ?? []),
                'penjelasan_html' => $this->strategiJalurKritis($kesiapan['jalur_kritis'] ?? []),
                'bagian_lain' => array_values(array_filter([
                    $this->bagianKelembagaan($kesiapan['struktur_kelembagaan'] ?? []),
                    $this->bagianRisiko($kesiapan['manajemen_risiko'] ?? []),
                    ($kesiapan['dokumen_lampiran'] ?? []) ? ['judul' => 'Dokumen Lampiran Kesiapan', 'isi_html' => $this->daftar($kesiapan['dokumen_lampiran'])] : null,
                ])),
            ],
            'dokumen' => $this->dokumen($d),
        ];
    }

    // ------------------------------------------------------------------ bantu

    protected function jalurKritis(array $items): array
    {
        $kode = [];
        foreach (array_values(array_slice($items, 0, 200)) as $i => $j) {
            $id = (string) ($j['id'] ?? $i + 1);
            $kode[$id] = strlen($id) <= 30 && preg_match('/^[\w.\-]+$/u', $id) ? $id : 'J'.($i + 1);
        }

        return array_values(array_map(fn ($j, $i) => [
            'kode' => $kode[(string) ($j['id'] ?? $i + 1)],
            'kegiatan' => Str::limit(trim((string) ($j['kegiatan'] ?? '')) ?: '(tanpa nama kegiatan)', 1990, ''),
            'target_selesai' => isset($j['periode']) ? Str::limit((string) $j['periode'], 100, '') : null,
            // Pendahulu yang tidak dikenal diteruskan apa adanya agar tercatat sebagai peringatan impor.
            'sebelumnya' => array_values(array_map(fn ($p) => $kode[(string) $p] ?? Str::limit((string) $p, 30, ''), (array) ($j['pendahulu'] ?? []))),
            'jalur_kritis' => (bool) ($j['kritis'] ?? false),
        ], array_values(array_slice($items, 0, 200)), array_keys(array_values(array_slice($items, 0, 200)))));
    }

    protected function strategiJalurKritis(array $items): ?string
    {
        $baris = array_filter(array_map(fn ($j) => filled($j['strategi'] ?? null) ? [$j['id'] ?? '', $j['kegiatan'] ?? '', $j['strategi']] : null, $items));

        return $baris ? '<p><b>Strategi pelaksanaan per kegiatan</b></p>'.$this->tabel(['Kode', 'Kegiatan', 'Strategi'], $baris) : null;
    }

    protected function dokumen(array $d): array
    {
        $hasil = [];
        if ($url = $d['files']['dokumen_usulan'] ?? null) {
            $hasil[] = ['jenis' => 'PDF_USULAN', 'judul' => 'Dokumen PDF Usulan', 'nama_berkas' => $d['kode_usulan'].'.pdf', 'url' => $url];
        }
        $meta = collect($d['attachments'] ?? [])->keyBy('id');
        $peta = config('psn_dashboard.psi.sif.jenis_lampiran');
        foreach ($d['files']['lampiran'] ?? [] as $l) {
            [$jenis, $judul] = $peta[$l['type'] ?? 'lainnya'] ?? ['LAMPIRAN', Str::headline((string) ($l['type'] ?? 'Lampiran'))];
            $m = $meta[$l['id'] ?? null] ?? [];
            $hasil[] = array_filter(['jenis' => $jenis, 'judul' => $judul, 'nama_berkas' => Str::limit((string) ($l['original_name'] ?? $m['original_name'] ?? ''), 250, '') ?: null,
                'url' => $l['url'] ?? null, 'ukuran' => isset($m['file_size']) ? (int) $m['file_size'] : null], fn ($v) => $v !== null);
        }

        return array_values(array_filter($hasil, fn ($x) => filled($x['url'] ?? null)));
    }

    protected function bagianProyekList(array $d): ?array
    {
        $daftar = $d['proyek_list'] ?? [];
        if (($d['template_variant'] ?? 'tunggal') !== 'jamak' || count($daftar) < 2) {
            return null;
        }

        return ['judul' => 'Daftar Proyek (usulan jamak)', 'isi_html' => $this->tabel(['Nama proyek', 'Lokasi', 'Tujuan', 'Pemilik', 'Lingkup', 'Indikasi jadwal'],
            array_map(fn ($p) => [$p['nama_proyek'] ?? '', $p['lokasi_proyek'] ?? '', $p['tujuan_proyek'] ?? '', $p['pemilik_proyek'] ?? '', $p['lingkup_proyek'] ?? '', $p['indikasi_jadwal'] ?? ''], $daftar))];
    }

    protected function bagianKriteriaUtama(array $k): ?array
    {
        $html = '';
        foreach (['rpjmn_17' => 'Dukungan terhadap sasaran RPJMN (17 butir)', 'hasil_cepat_8' => 'Program Hasil Terbaik Cepat (8 butir)'] as $kunci => $judul) {
            if ($baris = $this->barisYaTidak($k[$kunci] ?? [])) {
                $html .= "<p><b>{$judul}</b></p>".$this->tabel(['No', 'Butir', 'Ya/Tidak', 'Alasan'], $baris);
            }
        }
        $html .= $this->kunciNilai(['Konsentrasi dan sinergi' => $k['konsentrasi_sinergi'] ?? null, 'Selesai/berfungsi 2029' => $k['selesai_2029'] ?? null]);

        return $html !== '' ? ['judul' => 'Kriteria Utama (isian pengusul)', 'isi_html' => $html] : null;
    }

    protected function bagianKriteriaPendukung(array $k): ?array
    {
        $html = $this->kunciNilai(['Tematik, holistik, integratif, spasial' => $k['tematik_holistik'] ?? null, 'Persebaran kegiatan' => $k['persebaran_kegiatan'] ?? null,
            'RPJMN / RPJMD / RKAP' => $k['rpjmn_rpjmd_rkap'] ?? null]);
        if ($baris = $this->barisYaTidak($k['multi_kontribusi'] ?? [])) {
            $html .= '<p><b>Multi kontribusi</b></p>'.$this->tabel(['No', 'Butir', 'Ya/Tidak', 'Alasan'], $baris);
        }

        return $html !== '' ? ['judul' => 'Kriteria Pendukung (isian pengusul)', 'isi_html' => $html] : null;
    }

    protected function bagianKriteriaKesiapan(array $k): ?array
    {
        $html = $this->kunciNilai(['Rencana pembiayaan' => $k['rencana_pembiayaan'] ?? null,
            'Infrastruktur ekonomi berlaku' => isset($k['infrastruktur_ekonomi_applicable']) ? ($k['infrastruktur_ekonomi_applicable'] ? 'Ya' : 'Tidak') : null,
            'Infrastruktur ekonomi' => $k['infrastruktur_ekonomi'] ?? null, 'Pengadaan lahan' => $k['pengadaan_lahan'] ?? null]);

        return $html !== '' ? ['judul' => 'Kriteria Kesiapan (isian pengusul)', 'isi_html' => $html] : null;
    }

    protected function bagianKunciNilai(string $judul, array $data): ?array
    {
        $html = $this->kunciNilai($data);

        return $html !== '' ? ['judul' => $judul, 'isi_html' => $html] : null;
    }

    protected function bagianCatatanBappenas(array $c): ?array
    {
        $html = $this->kunciNilai(['Catatan umum' => $c['umum'] ?? null] + collect($c['per_poin'] ?? [])->mapWithKeys(fn ($v, $k) => ["Poin {$k}" => $v])->all());

        return $html !== '' ? ['judul' => 'Catatan Review Bappenas (SIF)', 'isi_html' => $html] : null;
    }

    protected function bagianKelembagaan(array $s): ?array
    {
        $pihak = collect($s['pihak'] ?? [])->keyBy('id');
        if ($pihak->isEmpty()) {
            return null;
        }
        $html = $this->tabel(['Pihak', 'Kategori', 'Tingkat'], $pihak->sortBy('tingkat')->map(fn ($p) => [$p['nama'] ?? '', Str::headline((string) ($p['kategori'] ?? '')), $p['tingkat'] ?? ''])->values()->all());
        if ($relasi = $s['relasi'] ?? []) {
            $html .= '<p><b>Relasi</b></p>'.$this->tabel(['Dari', 'Ke', 'Hubungan', 'Dua arah'], array_map(fn ($r) => [
                $pihak[$r['dari'] ?? null]['nama'] ?? ($r['dari'] ?? ''), $pihak[$r['ke'] ?? null]['nama'] ?? ($r['ke'] ?? ''), $r['label'] ?? '', ! empty($r['dua_arah']) ? 'Ya' : 'Tidak'], $relasi));
        }

        return ['judul' => 'Struktur Kelembagaan', 'isi_html' => $html];
    }

    protected function bagianRisiko(array $r): ?array
    {
        return $r ? ['judul' => 'Manajemen Risiko', 'isi_html' => $this->tabel(['Risiko', 'Jalur kritis', 'Penyebab', 'Kemungkinan', 'Dampak', 'Mitigasi'],
            array_map(fn ($x) => [$x['risiko'] ?? '', $x['kode_jalur_kritis'] ?? '', $x['penyebab'] ?? '', $x['kemungkinan'] ?? '', $x['dampak'] ?? '', $x['mitigasi'] ?? ''], $r))] : null;
    }

    protected function barisYaTidak(array $baris): array
    {
        return array_map(fn ($b) => [$b['no'] ?? '', $b['label'] ?? '', $this->yaTidak($b['ya_tidak'] ?? null), $b['alasan'] ?? ''], $baris);
    }

    protected function yaTidak($v): string
    {
        return match (true) {
            $v === true, in_array(Str::lower((string) $v), ['ya', 'y', '1', 'true'], true) => 'Ya',
            $v === false, in_array(Str::lower((string) $v), ['tidak', 't', '0', 'false'], true) => 'Tidak',
            default => (string) $v,
        };
    }

    /** Objek kunci-nilai (boleh bersarang) -> tabel dua kolom; nilai kosong dilewati. */
    protected function kunciNilai(array $data): string
    {
        $baris = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $v = collect($v)->filter(fn ($x) => filled($x) && ! is_array($x))->map(fn ($x, $kk) => Str::headline((string) $kk).': '.(is_bool($x) ? ($x ? 'Ya' : 'Tidak') : $x))->implode('; ');
            } elseif (is_bool($v)) {
                $v = $v ? 'Ya' : 'Tidak';
            }
            if (filled($v)) {
                $baris[] = [is_string($k) && ! str_contains($k, ' ') ? Str::headline($k) : $k, $v];
            }
        }

        return $baris ? $this->tabel(['Butir', 'Isian'], $baris) : '';
    }

    protected function tabel(array $kepala, array $baris): string
    {
        $e = fn ($v) => nl2br(e(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE)));

        return '<table><thead><tr>'.implode('', array_map(fn ($k) => '<th>'.$e($k).'</th>', $kepala)).'</tr></thead><tbody>'
            .implode('', array_map(fn ($b) => '<tr>'.implode('', array_map(fn ($v) => '<td>'.$e($v).'</td>', $b)).'</tr>', $baris)).'</tbody></table>';
    }

    protected function daftar(array $item): string
    {
        return '<ul>'.implode('', array_map(fn ($x) => '<li>'.e(is_scalar($x) ? (string) $x : json_encode($x, JSON_UNESCAPED_UNICODE)).'</li>', $item)).'</ul>';
    }

    /** Teks polos -> paragraf HTML; HTML dari SIF dibiarkan (disaring HtmlAman saat impor). */
    protected function paragraf($v): ?string
    {
        if (! is_string($v) || trim($v) === '') {
            return null;
        }

        return preg_match('/<(p|ul|ol|br|table|div)\b/i', $v) ? $v : '<p>'.nl2br(e(trim($v))).'</p>';
    }

    /** "Rp 12.500.000.000", "12,5 triliun", "800 miliar" -> rupiah penuh; selain itu null. */
    protected function rupiah($v): ?string
    {
        if (is_int($v) || is_float($v)) {
            return number_format((float) $v, 2, '.', '');
        }
        $t = Str::lower(trim((string) $v));
        if (! preg_match('/^(?:rp\.?\s*)?([\d.]+(?:,\d+)?|\d+(?:\.\d+)?)\s*(triliun|t|miliar|milyar|m|juta|jt)?\.?$/u', $t, $m)) {
            return null;
        }
        $angka = isset($m[2]) && $m[2] !== '' && preg_match('/^\d+\.\d{1,2}$/', $m[1]) ? (float) $m[1] : (float) str_replace(['.', ','], ['', '.'], $m[1]);
        $kali = ['triliun' => 1e12, 't' => 1e12, 'miliar' => 1e9, 'milyar' => 1e9, 'm' => 1e9, 'juta' => 1e6, 'jt' => 1e6][$m[2] ?? ''] ?? 1;

        return number_format($angka * $kali, 2, '.', '');
    }

    /** Kode provinsi yang namanya disebut di teks lokasi (nama terpanjang dicocokkan lebih dulu). */
    protected function provinsi(string $teks): array
    {
        $teks = ' '.Str::lower($teks).' ';
        $kode = [];
        foreach (DB::table('ref_wilayah')->where('level', 1)->get(['kode', 'nama'])->sortByDesc(fn ($p) => strlen($p->nama)) as $p) {
            $nama = Str::lower(preg_replace('/^(provinsi|prov\.?)\s+/i', '', $p->nama));
            if ($nama !== '' && preg_match('/\b'.preg_quote($nama, '/').'\b/u', $teks)) {
                $kode[] = (string) $p->kode;
                $teks = str_replace($nama, ' ', $teks);
            }
        }
        sort($kode);

        return $kode;
    }

    protected function tanggal($v): ?Carbon
    {
        try {
            return filled($v) ? Carbon::parse($v) : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function urutkan($v)
    {
        if (! is_array($v)) {
            return $v;
        }
        if (! array_is_list($v)) {
            ksort($v);
        }

        return array_map(fn ($x) => $this->urutkan($x), $v);
    }
}
