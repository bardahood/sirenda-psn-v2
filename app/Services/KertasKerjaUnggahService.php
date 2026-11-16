<?php

namespace App\Services;

use App\Models\Psn;
use App\Models\User;
use App\Support\Evaluasi\FormatRingkas;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use RuntimeException;
use Throwable;

/**
 * Unggah kertas kerja Excel (format ringkas 92 kolom, hasil "Unduh kertas kerja" maupun template resmi
 * "Input Form Pengendalian PSN"). Alur: baca -> pratinjau (hanya sel yang berbeda dari kertas kerja saat ini)
 * -> terapkan (memakai KertasKerjaIsianService, sehingga validasi, izin, dan riwayat sama dengan isian langsung).
 * Kolom profil (A1-A5, A7, A9-A11, B1) dan kolom otomatis diabaikan.
 */
class KertasKerjaUnggahService
{
    public const MAKS_BARIS = 2000;

    protected const KETERANGAN = 'Unggah kertas kerja';

    /** Kelompok rincian: kunci kolom penyusunnya. */
    protected const RINCIAN = [
        'b3a' => ['b3a_jenis', 'b3a_status', 'b3a_tanggal', 'b3a_ket'],
        'b3b' => ['b3b_jenis', 'b3b_status', 'b3b_tanggal', 'b3b_ket'],
        'c2' => ['c2_uraian', 'c2_dukungan'],
        'lampiran' => ['lamp_jenis', 'lamp_judul', 'lamp_bentuk', 'lamp_tautan'],
    ];

    public function __construct(protected EvaluasiStatusService $layanan, protected FormatRingkas $format, protected KertasKerjaIsianService $isian) {}

    /**
     * Baca berkas: pilih sheet format ringkas, temukan baris judul ("Nama PSN" di kolom C), ambil baris ber-Kode PSN.
     *
     * @return array{sheet:string, baris: array<int, array{baris_excel:int, kode:string, sel:array<string,mixed>}>}
     */
    public function baca(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $buku = $reader->load($path);
        } catch (Throwable $e) {
            throw new RuntimeException('Berkas tidak dapat dibaca sebagai Excel (.xlsx): '.$e->getMessage());
        }

        $kandidat = [];
        foreach ($buku->getWorksheetIterator() as $ws) {
            $nama = $ws->getTitle();
            if (str_starts_with(mb_strtolower($nama), 'contoh')) {
                continue;
            }
            $kandidat[] = [str_contains(mb_strtolower($nama), 'format ringkas') ? 0 : 1, $ws];
        }
        usort($kandidat, fn ($a, $b) => $a[0] <=> $b[0]);

        $kolom = $this->format->kolom();
        foreach ($kandidat as [, $ws]) {
            $judul = null;
            for ($r = 1; $r <= 15; $r++) {
                if (trim((string) $ws->getCell("C{$r}")->getValue()) === 'Nama PSN') {
                    $judul = $r;
                }
            }
            if (! $judul) {
                continue;
            }
            if (Coordinate::columnIndexFromString($ws->getHighestDataColumn()) < count($kolom) - 3) {
                throw new RuntimeException("Sheet \"{$ws->getTitle()}\" bukan format ringkas lengkap (harus sampai kolom CN, 92 kolom).");
            }
            $akhir = (int) $ws->getHighestDataRow();
            if ($akhir - $judul > self::MAKS_BARIS) {
                throw new RuntimeException('Terlalu banyak baris (maks. '.self::MAKS_BARIS.').');
            }
            $baris = [];
            $huruf = Coordinate::stringFromColumnIndex(count($kolom));
            foreach ($ws->rangeToArray('A'.($judul + 1).":{$huruf}{$akhir}", null, false, false, false) as $i => $nilai) {
                $kode = trim((string) ($nilai[1] ?? ''));
                if ($kode === '') {
                    continue;
                }
                $sel = [];
                foreach ($kolom as $j => $k) {
                    $sel[$k['kunci']] = $nilai[$j] ?? null;
                }
                $baris[] = ['baris_excel' => $judul + 1 + $i, 'kode' => $kode, 'sel' => $sel];
            }

            return ['sheet' => $ws->getTitle(), 'baris' => $baris];
        }

        throw new RuntimeException('Sheet format ringkas tidak ditemukan. Gunakan berkas "Unduh kertas kerja" atau template Form PSN Format Ringkas (kolom C berjudul "Nama PSN").');
    }

    /**
     * Bandingkan isi berkas dengan kertas kerja saat ini (dalam cakupan pengguna).
     *
     * @return array{sheet:string, psn: array, tidak_dikenal: array, ringkasan: array}
     */
    public function pratinjau(array $bacaan, User $u): array
    {
        $c = config('psn_dashboard.evaluasi_status');
        $editor = $this->format->editor();
        $label = collect($this->format->kolom())->mapWithKeys(fn ($k) => [$k['kunci'] => $this->labelKolom($k)])->all();
        $labelPendek = collect($this->format->kolom())->mapWithKeys(fn ($k) => [$k['kunci'] => $k['label']])->all();
        $daftar = $this->layanan->daftar();
        $sekarang = $this->format->semua($daftar)->keyBy(fn ($b) => mb_strtolower(trim((string) $b['r']['kode'])));

        $hasil = [];
        $tidakDikenal = [];
        $dilihat = [];
        foreach ($bacaan['baris'] as $baris) {
            $kunciKode = mb_strtolower($baris['kode']);
            $b = $sekarang[$kunciKode] ?? null;
            if (! $b) {
                $tidakDikenal[] = ['baris_excel' => $baris['baris_excel'], 'kode' => $baris['kode'], 'alasan' => 'Kode PSN tidak ditemukan di antara PSN aktif dalam cakupan Anda'];

                continue;
            }
            if (isset($dilihat[$kunciKode])) {
                $tidakDikenal[] = ['baris_excel' => $baris['baris_excel'], 'kode' => $baris['kode'], 'alasan' => "Kode PSN ganda (sudah ada di baris {$dilihat[$kunciKode]})"];

                continue;
            }
            $dilihat[$kunciKode] = $baris['baris_excel'];

            $sel = $baris['sel'];
            $perubahan = [];
            // Jenis PSN baris: dari berkas bila valid, bila tidak dari isian saat ini.
            $jenisBerkas = $this->pilihan($sel['a12'] ?? null, $c['jenis_psn']);
            $jenis = $jenisBerkas['ok'] && $jenisBerkas['nilai'] ? $jenisBerkas['nilai'] : $b['r']['jenis'];

            foreach ($editor as $kunci => $ed) {
                if ($ed['tipe'] === 'rincian') {
                    continue;
                }
                $mentah = $sel[$kunci] ?? null;
                if ($ed['komponen'] && $ed['jenis'] !== $jenis) {
                    if (! $this->kosong($mentah) && $this->normal($mentah) !== $this->normal($b['nilai'][$kunci] ?? null)) {
                        $perubahan[] = $this->ubah($kunci, $label[$kunci], $b, $mentah, null, 'Kolom ini untuk PSN '.$c['jenis_psn'][$ed['jenis']].' (A12 = '.$c['jenis_psn'][$jenis].'); diabaikan.');
                    }

                    continue;
                }
                $urai = $this->urai($kunci, $ed, $mentah);
                if (! $urai['ok']) {
                    $perubahan[] = $this->ubah($kunci, $label[$kunci], $b, $mentah, null, $urai['galat']);

                    continue;
                }
                $baru = $urai['nilai'];
                $lama = $b['mentah'][$kunci] ?? null;
                if ($kunci === 'tl' && $baru === null) {
                    continue; // tindak lanjut kosong = mengikuti hasil pengecekan
                }
                if ($ed['komponen'] && $baru === null && in_array($ed['kolom'], $b['r']['capaian'][$ed['komponen']]['dari_profil'], true)) {
                    continue; // kosong dan memang memakai data profil
                }
                if ($this->sama($baru, $lama) && ($kunci !== 'c1' || $this->sama($urai['lain'] ?? null, $b['mentah']['c1_lainnya'] ?? null))) {
                    continue;
                }
                $galat = $u->can("evaluasi.{$ed['izin']}") ? $this->validasi($kunci, $baru, $urai['lain'] ?? null) : 'Anda tidak berwenang mengisi kolom ini.';
                $perubahan[] = $this->ubah($kunci, $label[$kunci], $b, $mentah, ['nilai' => $baru, 'lain' => $urai['lain'] ?? null], $galat);
            }

            foreach (self::RINCIAN as $kelompok => $kunciKolom) {
                if (($kelompok === 'b3a' && $jenis !== 'FISIK') || ($kelompok === 'b3b' && $jenis !== 'NON_FISIK')) {
                    continue;
                }
                $teksBerkas = implode("\n¦", array_map(fn ($k) => $this->normal($sel[$k] ?? null), $kunciKolom));
                $teksKini = implode("\n¦", array_map(fn ($k) => $this->normal($b['nilai'][$k] ?? null), $kunciKolom));
                if ($teksBerkas === $teksKini) {
                    continue;
                }
                $urai = match ($kelompok) {
                    'b3a', 'b3b' => $this->uraiMilestone($sel, $kelompok, $kelompok === 'b3a' ? 'FISIK' : 'NON_FISIK'),
                    'c2' => $this->uraiIsu($sel),
                    'lampiran' => $this->uraiLampiran($sel),
                };
                $judulKelompok = ['b3a' => 'B3.a Milestone', 'b3b' => 'B3.b Milestone', 'c2' => 'C2 Uraian isu', 'lampiran' => 'Daftar lampiran'][$kelompok];
                $galat = $urai['galat'] ?? ($u->can('evaluasi.input') ? null : 'Anda tidak berwenang mengisi kolom ini.');
                $blok = fn (array $sumber) => trim(implode("\n\n", array_filter(array_map(fn ($k) => filled($sumber[$k] ?? null) && is_scalar($sumber[$k])
                    ? $labelPendek[$k].":\n".trim((string) $sumber[$k]) : null, $kunciKolom))));
                $perubahan[] = ['kunci' => $kelompok, 'label' => $judulKelompok, 'lama' => $blok($b['nilai']), 'baru' => $blok($sel),
                    'rincian' => $urai['baris'] ?? null, 'kelompok' => $kelompok === 'c2' ? 'isu' : ($kelompok === 'lampiran' ? 'lampiran' : 'milestone'), 'galat' => $galat];
            }

            if ($perubahan) {
                $hasil[] = ['psn_id' => $b['r']['psn_id'], 'kode' => $b['r']['kode'], 'nama' => $b['r']['nama'], 'baris_excel' => $baris['baris_excel'],
                    'status_akhir' => $b['r']['status_akhir'], 'perubahan' => $perubahan];
            }
        }

        $semua = collect($hasil)->flatMap(fn ($p) => $p['perubahan']);

        return ['sheet' => $bacaan['sheet'], 'psn' => $hasil, 'tidak_dikenal' => $tidakDikenal, 'ringkasan' => [
            'baris' => count($bacaan['baris']), 'dikenali' => count($dilihat), 'psn_berubah' => count($hasil),
            'sel_valid' => $semua->whereNull('galat')->count(), 'sel_galat' => $semua->whereNotNull('galat')->count(), 'tidak_dikenal' => count($tidakDikenal),
        ]];
    }

    /**
     * Terapkan perubahan valid untuk PSN terpilih. Setiap PSN dalam satu transaksi.
     *
     * @return array{psn:int, sel:int, galat:array<int,string>}
     */
    public function terapkan(array $pratinjau, array $psnTerpilih, User $u): array
    {
        $hasil = ['psn' => 0, 'sel' => 0, 'galat' => []];
        foreach ($pratinjau['psn'] as $p) {
            if (! in_array($p['psn_id'], $psnTerpilih, true)) {
                continue;
            }
            $valid = array_values(array_filter($p['perubahan'], fn ($x) => $x['galat'] === null));
            if (! $valid) {
                continue;
            }
            $psn = Psn::find($p['psn_id']);
            $kini = $psn ? $this->layanan->satu($psn) : null;
            if (! $kini) {
                $hasil['galat'][] = "{$p['kode']}: PSN tidak lagi tersedia.";

                continue;
            }
            try {
                DB::transaction(function () use ($psn, $kini, $valid, $u, &$hasil, $p) {
                    $e = $this->isian->evaluasi($psn, $kini['evaluasi'], 'Form diterima (unggah kertas kerja)');
                    foreach ($valid as $x) {
                        if (isset($x['kelompok'])) {
                            abort_unless($u->can('evaluasi.input'), 403);
                            $this->isian->isiRincian($e->fresh(), $x['kelompok'], $x['rincian'], self::KETERANGAN);
                        } else {
                            abort_unless($u->can('evaluasi.'.$this->format->editor()[$x['kunci']]['izin']), 403);
                            if ($g = $this->isian->isi($e->fresh(), $x['kunci'], $x['nilai']['nilai'], $x['nilai']['lain'], self::KETERANGAN)) {
                                $hasil['galat'][] = "{$p['kode']} {$x['label']}: {$g}";

                                continue;
                            }
                        }
                        $hasil['sel']++;
                    }
                });
                $hasil['psn']++;
            } catch (Throwable $e) {
                $hasil['galat'][] = "{$p['kode']}: gagal disimpan ({$e->getMessage()}); perubahan PSN ini dibatalkan.";
            }
        }

        return $hasil;
    }

    // ------------------------------------------------------------------ penguraian

    /** @return array{ok:bool, nilai?:mixed, lain?:?string, galat?:string} */
    protected function urai(string $kunci, array $ed, $v): array
    {
        if ($this->kosong($v)) {
            return ['ok' => true, 'nilai' => $ed['tipe'] === 'multi' ? [] : null, 'lain' => null];
        }

        return match ($ed['tipe']) {
            'angka' => $this->angka($v, in_array($kunci, ['a6', 'd3_tahun'], true)),
            'teks', 'panjang' => ['ok' => true, 'nilai' => $this->teks($v)],
            'tanggal' => $this->tanggal($v),
            'pilihan' => $this->pilihan($v, $ed['opsi']),
            'multi' => $this->multi($kunci, $v, $ed['opsi']),
            default => ['ok' => false, 'galat' => 'Kolom tidak dapat diunggah.'],
        };
    }

    protected function angka($v, bool $tahun): array
    {
        if ($v instanceof DateTimeInterface) {
            return ['ok' => false, 'galat' => 'Harus berupa angka.'];
        }
        if (! is_int($v) && ! is_float($v)) {
            $t = str_replace([' ', "\u{00A0}", 'Rp', 'rp', '%'], '', (string) $v);
            if (str_contains($t, ',') && str_contains($t, '.')) {
                $t = str_replace(['.', ','], ['', '.'], $t);
            } elseif (str_contains($t, ',')) {
                $t = str_replace(',', '.', $t);
            }
            if (! is_numeric($t)) {
                return ['ok' => false, 'galat' => "\"{$v}\" bukan angka."];
            }
            $v = (float) $t;
        }
        if ($tahun) {
            return fmod((float) $v, 1) == 0 ? ['ok' => true, 'nilai' => (int) $v] : ['ok' => false, 'galat' => 'Tahun harus bilangan bulat.'];
        }

        return ['ok' => true, 'nilai' => (float) $v];
    }

    protected function teks($v): string
    {
        return match (true) {
            $v instanceof DateTimeInterface => $v->format('d-m-Y'),
            is_float($v) || is_int($v) => $this->normal($v),
            default => trim((string) $v),
        };
    }

    protected function tanggal($v): array
    {
        try {
            $t = match (true) {
                $v instanceof DateTimeInterface => CarbonImmutable::instance($v),
                is_int($v) || is_float($v) => CarbonImmutable::instance(TanggalExcel::excelToDateTimeObject($v)),
                (bool) preg_match('/^\d{1,2}[-\/.]\d{1,2}[-\/.]\d{4}$/', trim((string) $v)) => CarbonImmutable::createFromFormat('d-m-Y', preg_replace('/[\/.]/', '-', trim((string) $v))),
                default => CarbonImmutable::parse((string) $v),
            };

            return ['ok' => true, 'nilai' => $t->toDateString()];
        } catch (Throwable) {
            return ['ok' => false, 'galat' => "\"{$v}\" bukan tanggal (gunakan dd-mm-yyyy)."];
        }
    }

    /** Label Daftar Pilihan (atau kodenya) -> kode. */
    protected function pilihan($v, array $opsi): array
    {
        if ($this->kosong($v)) {
            return ['ok' => true, 'nilai' => null];
        }
        $t = $this->normal($v);
        foreach ($opsi as $kode => $label) {
            if ($t === $this->normal($label) || $t === $this->normal((string) $kode)) {
                return ['ok' => true, 'nilai' => (string) $kode];
            }
        }

        return ['ok' => false, 'galat' => "\"{$v}\" tidak ada di Daftar Pilihan."];
    }

    /** "1. Label" per baris -> larik kode; C1 "11. Lainnya: teks" -> lain. */
    protected function multi(string $kunci, $v, array $opsi): array
    {
        $kodeUrut = array_map('strval', array_keys($opsi));
        $kode = [];
        $lain = null;
        foreach ($this->butir($v) as $no => $teks) {
            if ($kunci === 'c1' && ($no === 11 || preg_match('/^lainnya\b/i', $teks))) {
                $lain = trim(preg_replace('/^lainnya\s*[:\-]?\s*/i', '', $teks)) ?: null;

                continue;
            }
            $cocok = $this->pilihan($teks, $opsi);
            if ($cocok['ok'] && $cocok['nilai'] !== null) {
                $kode[] = $cocok['nilai'];
            } elseif ($no !== null && isset($kodeUrut[$no - 1])) {
                $kode[] = $kodeUrut[$no - 1];
            } else {
                return ['ok' => false, 'galat' => "\"{$teks}\" tidak ada di Daftar Pilihan."];
            }
        }

        return ['ok' => true, 'nilai' => array_values(array_unique($kode)), 'lain' => $lain];
    }

    protected function uraiMilestone(array $sel, string $awal, string $jenis): array
    {
        $c = config('psn_dashboard.evaluasi_status');
        $daftar = $c['milestone'][$jenis];
        $kodeUrut = array_keys($daftar);
        $jenisB = $this->butir($sel["{$awal}_jenis"] ?? null);
        $status = $this->butir($sel["{$awal}_status"] ?? null);
        $tgl = $this->butir($sel["{$awal}_tanggal"] ?? null);
        $ket = $this->butir($sel["{$awal}_ket"] ?? null);
        $baris = [];
        foreach (array_unique(array_merge(array_keys($jenisB), array_keys($status), array_keys($tgl), array_keys($ket))) as $no) {
            if (! is_int($no) || ! isset($kodeUrut[$no - 1])) {
                return ['galat' => "Nomor milestone \"{$no}\" tidak dikenal (1 sampai ".count($kodeUrut).')'];
            }
            $kode = $kodeUrut[$no - 1];
            $st = $this->pilihan($this->strip($status[$no] ?? null), $c['status_milestone']);
            if (! $st['ok']) {
                return ['galat' => "Milestone {$no}: {$st['galat']}"];
            }
            $t = $this->strip($tgl[$no] ?? null);
            $tt = $t === null ? ['ok' => true, 'nilai' => null] : $this->tanggal($t);
            if (! $tt['ok']) {
                return ['galat' => "Milestone {$no}: {$tt['galat']}"];
            }
            $nama = $this->strip($jenisB[$no] ?? null);
            $baris[] = ['kode' => $kode, 'nama_lain' => str_starts_with($kode, 'LAIN') && $nama && $this->normal($nama) !== $this->normal($daftar[$kode]) ? $nama : null,
                'status' => $st['nilai'], 'tanggal' => $tt['nilai'], 'keterangan' => $this->strip($ket[$no] ?? null)];
        }
        usort($baris, fn ($a, $b) => array_search($a['kode'], $kodeUrut) <=> array_search($b['kode'], $kodeUrut));

        return ['baris' => $baris];
    }

    /** "[Tipologi 1; menghambat jalur kritis] uraian – Upaya: ..." per butir; dukungan dicocokkan menurut nomor. */
    protected function uraiIsu(array $sel): array
    {
        $c = config('psn_dashboard.evaluasi_status');
        $uraian = $this->butir($sel['c2_uraian'] ?? null);
        $dukungan = $this->butir($sel['c2_dukungan'] ?? null);
        if (count($uraian) > $c['maks_isu']) {
            return ['galat' => "Maksimal {$c['maks_isu']} isu."];
        }
        $baris = [];
        foreach ($uraian as $no => $teks) {
            $tipologi = null;
            $kritis = null;
            if (preg_match('/^\[([^\]]*)\]\s*(.*)$/s', $teks, $m)) {
                $teks = $m[2];
                if (preg_match('/tipologi\s*(\d+)/i', $m[1], $t)) {
                    $tipologi = $t[1];
                    if (! isset($c['tipologi_isu'][$tipologi])) {
                        return ['galat' => "Isu {$no}: tipologi {$tipologi} tidak dikenal (1 sampai 11)."];
                    }
                }
                $kritis = preg_match('/tidak\s+menghambat/i', $m[1]) ? false : (preg_match('/menghambat/i', $m[1]) ? true : null);
            }
            $bagian = preg_split('/\s+[–-]\s*Upaya\s*:\s*/u', $teks, 2);
            $baris[] = ['uraian' => trim($bagian[0]), 'tipologi' => $tipologi, 'jalur_kritis' => $kritis === null ? '' : (string) (int) $kritis,
                'upaya' => isset($bagian[1]) ? trim($bagian[1]) : null, 'dukungan' => $this->strip($dukungan[$no] ?? null)];
        }

        return ['baris' => $baris];
    }

    protected function uraiLampiran(array $sel): array
    {
        $c = config('psn_dashboard.evaluasi_status');
        $jenis = $this->butir($sel['lamp_jenis'] ?? null);
        $judul = $this->butir($sel['lamp_judul'] ?? null);
        $bentuk = $this->butir($sel['lamp_bentuk'] ?? null);
        $tautan = $this->butir($sel['lamp_tautan'] ?? null);
        $baris = [];
        foreach (array_unique(array_merge(array_keys($jenis), array_keys($judul), array_keys($bentuk), array_keys($tautan))) as $no) {
            $j = $this->pilihan($this->strip($jenis[$no] ?? null), $c['lampiran_jenis']);
            $b = $this->pilihan($this->strip($bentuk[$no] ?? null), $c['lampiran_bentuk']);
            if (! $j['ok'] || ! $b['ok']) {
                return ['galat' => "Lampiran {$no}: ".($j['galat'] ?? $b['galat'])];
            }
            if (! $this->strip($judul[$no] ?? null)) {
                return ['galat' => "Lampiran {$no}: judul wajib diisi."];
            }
            $baris[] = ['jenis' => $j['nilai'], 'judul' => $this->strip($judul[$no]), 'bentuk' => $b['nilai'], 'tautan' => $this->strip($tautan[$no] ?? null)];
        }
        if (count($baris) > $c['maks_lampiran']) {
            return ['galat' => "Maksimal {$c['maks_lampiran']} lampiran."];
        }

        return ['baris' => $baris];
    }

    /** Teks sel "1. ...\n2. ..." -> [nomor => teks]; baris lanjutan digabung ke butir sebelumnya. */
    protected function butir($v): array
    {
        if ($this->kosong($v)) {
            return [];
        }
        $hasil = [];
        $no = null;
        $urut = 0;
        foreach (preg_split('/\r\n|\r|\n/', (string) $v) as $baris) {
            $baris = trim($baris);
            if ($baris === '') {
                continue;
            }
            if (preg_match('/^(\d{1,2})\.\s*(.*)$/', $baris, $m)) {
                $no = (int) $m[1];
                $hasil[$no] = $m[2];
            } elseif ($no === null) {
                $no = ++$urut;
                $hasil[$no] = $baris;
            } else {
                $hasil[$no] .= ' '.$baris;
            }
        }

        return $hasil;
    }

    protected function validasi(string $kunci, $nilai, ?string $lain): ?string
    {
        $v = Validator::make(['nilai' => $nilai, 'nilai_lain' => $lain], $this->isian->aturan($kunci), [], ['nilai' => 'nilai']);

        return $v->fails() ? $v->errors()->first() : null;
    }

    protected function ubah(string $kunci, string $label, array $b, $mentah, ?array $nilai, ?string $galat): array
    {
        return ['kunci' => $kunci, 'label' => $label, 'lama' => (string) FormatRingkas::tampil($kunci, $b['nilai'][$kunci] ?? null),
            'baru' => match (true) {
                $kunci === 'pic_tanggal' && is_string($nilai['nilai'] ?? null) => CarbonImmutable::parse($nilai['nilai'])->format('d-m-Y'),
                $mentah instanceof DateTimeInterface => $mentah->format('d-m-Y'),
                default => is_scalar($mentah) ? (string) $mentah : '',
            }, 'nilai' => $nilai, 'galat' => $galat];
    }

    protected function labelKolom(array $k): string
    {
        $g = trim($k['grup']);

        return preg_match('/^A\d+$/', $g) ? "{$g} {$k['label']}" : "{$g}: {$k['label']}";
    }

    protected function sama($a, $b): bool
    {
        if (is_array($a) || is_array($b)) {
            $x = array_map('strval', (array) $a);
            $y = array_map('strval', (array) $b);
            sort($x);
            sort($y);

            return $x === $y;
        }
        if ($a === null || $a === '' || $b === null || $b === '') {
            return ($a === null || $a === '') && ($b === null || $b === '');
        }
        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 1e-6;
        }

        return $this->normal($a) === $this->normal($b);
    }

    protected function normal($v): string
    {
        if ($v instanceof DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        if (is_float($v) || is_int($v)) {
            return rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
        }

        return mb_strtolower(implode("\n", array_map(fn ($l) => preg_replace('/\s+/u', ' ', trim($l)), preg_split('/\r\n|\r|\n/', trim((string) $v)))));
    }

    protected function kosong($v): bool
    {
        return $v === null || (is_string($v) && trim($v) === '');
    }

    /** Teks butir; tanda kosong "–"/"-" dianggap null. */
    protected function strip(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);

        return $v === null || $v === '' || in_array($v, ['–', '-', '—'], true) ? null : $v;
    }
}
