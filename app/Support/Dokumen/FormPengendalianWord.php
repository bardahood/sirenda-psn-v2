<?php

namespace App\Support\Dokumen;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Mengisi templat Word resmi "Form Pengendalian PSN" (resources/templates/form-pengendalian-psn.docx)
 * dengan data FormPengendalianService. Tata letak, logo, dan kotak centang (content control Word)
 * tetap milik templat; teks isian ditambahkan berwarna biru agar mudah diperiksa PIC.
 *
 * Templat dibaca berdasarkan urutan tabel dan label barisnya; bila templat diganti, sesuaikan TABEL.
 */
class FormPengendalianWord
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const W14 = 'http://schemas.microsoft.com/office/word/2010/wordml';

    /** Nomor urut tabel (1-based) di body templat. */
    private const TABEL = ['A' => 3, 'B1' => 5, 'B2A' => 6, 'B2B' => 7, 'C2' => 13];

    private const BIRU = '0B4A8B';

    private DOMDocument $dok;

    private DOMXPath $xp;

    private int $idSdt = 990000;

    public function __construct(private string $templat) {}

    /** @return string isi berkas .docx */
    public function buat(array $d): string
    {
        $zip = new ZipArchive;
        $sementara = tempnam(sys_get_temp_dir(), 'fpw');
        if (! copy($this->templat, $sementara) || $zip->open($sementara) !== true) {
            throw new RuntimeException('Templat Form Pengendalian tidak dapat dibuka.');
        }
        $this->dok = new DOMDocument;
        $this->dok->loadXML($zip->getFromName('word/document.xml'));
        $this->xp = new DOMXPath($this->dok);
        $this->xp->registerNamespace('w', self::W);
        $this->xp->registerNamespace('w14', self::W14);

        $this->gantiPeriode($d);
        $this->isiBagianA($d);
        $this->isiB1($d['tahapan']);
        $this->isiB2($d);
        $this->isiC2($d['isu']);

        $zip->addFromString('word/document.xml', $this->dok->saveXML());
        $zip->close();
        $isi = file_get_contents($sementara);
        @unlink($sementara);

        return $isi;
    }

    // ------------------------------------------------------------------ bagian form

    /** Teks periode di templat (TW III 2026, 30 September 2026, ...) mengikuti cut-off yang dipilih. */
    private function gantiPeriode(array $d): void
    {
        $c = $d['cutoff'];
        $ganti = $d['batas_kembali'] ? ['Selasa, 17 November 2026' => $d['batas_kembali']] : [];
        if ($c) {
            $th = $c->tanggal_cutoff->year;
            $tw = $d['akhir_triwulan'] ? "TW {$d['triwulan']} {$th}" : $c->tanggal_cutoff->translatedFormat('F Y');
            $ganti += [
                '30 September 2026' => $c->tanggal_cutoff->translatedFormat('j F Y'),
                'Triwulan III 2026' => $d['akhir_triwulan'] ? "Triwulan {$d['triwulan']} {$th}" : $tw,
                'TW III 2026' => $tw,
                'TAHUN 2026' => "TAHUN {$th}",
                'RKP 2027' => 'RKP '.($th + 1),
                'akhir 2027' => 'akhir '.($th + 1),
            ];
        }
        foreach ($this->xp->query('//w:t') as $t) {
            $baru = strtr($t->textContent, $ganti);
            if ($baru !== $t->textContent) {
                $t->nodeValue = '';
                $t->appendChild($this->dok->createTextNode($baru));
            }
        }
    }

    private function isiBagianA(array $d): void
    {
        $a = $d['a'];
        $rp = fn ($v) => $v === null ? null : number_format($v, 0, ',', '.');
        $isi = [
            'A1' => [$a['nama'].($a['kode'] ? " ({$a['kode']})" : '')],
            'A2' => [$a['klaster']],
            'A3' => [implode('; ', $a['lokasi'])],
            'A4' => collect($a['kelembagaan'])->map(fn ($nama, $peran) => "{$peran}: ".implode('; ', $nama))->values()->all(),
            'A5' => $this->baris($a['dasar_penetapan']),
            'A7' => [$a['tahun_selesai'] !== null ? (string) $a['tahun_selesai'] : null],
            'A8' => [implode(', ', $a['sumber_dana'])],
            'A10' => $this->baris($a['deskripsi']),
            'A11' => $this->baris($a['output']),
        ];
        foreach ($isi as $no => $teks) {
            $this->tulis($this->sel(self::TABEL['A'], $no, 2), $teks);
        }
        // A9 berisi "Rp. ": angka ditambahkan di paragraf yang sama.
        $this->tulis($this->sel(self::TABEL['A'], 'A9', 2), [$rp($a['nilai_investasi_rp'])], sambung: true);
        if ($a['investasi_anomali']) {
            $this->tulis($this->sel(self::TABEL['A'], 'A9', 2), ['Nilai di SIRENDA ditandai perlu verifikasi; mohon dikoreksi.'], tambah: true);
        }
        if ($d['jenis']) {
            $this->centang($this->sel(self::TABEL['A'], 'A12', 2), $d['jenis'] === 'fisik' ? 'Fisik' : 'Non-Fisik');
        }
    }

    private function isiB1(?string $tahapan): void
    {
        $label = ['PENYIAPAN' => 'Penyiapan', 'TRANSAKSI' => 'Transaksi', 'KONSTRUKSI' => 'Konstruksi',
            'BEROPERASI_SEBAGIAN' => 'Beroperasi sebagian', 'KUMULATIF_SELESAI' => 'Kumulatif selesai'][$tahapan] ?? null;
        $label && $this->centang($this->sel(self::TABEL['B1'], 'B1', 2), $label);
    }

    private function isiB2(array $d): void
    {
        $b = $d['b2'];
        $n = fn ($v) => $v === null ? null : number_format($v, 2, ',', '.');
        $tahun = $d['cutoff']?->tanggal_cutoff->year;
        $ro = $b['ro'] && $b['ro'][1] ? "SIRENDA: {$b['ro'][0]} dari {$b['ro'][1]} KP/RO tercapai." : null;
        $biaya = array_filter([
            $b['pembiayaan_target'] !== null || $b['pembiayaan_realisasi'] !== null
                ? "SIRENDA (KP/RO tahun {$tahun}): pagu Rp ".($n($b['pembiayaan_target']) ?? '-').' miliar, realisasi Rp '.($n($b['pembiayaan_realisasi']) ?? '-').' miliar.' : null,
            $d['a']['sumber_dana'] ? 'Sumber dana: '.implode(', ', $d['a']['sumber_dana']) : null,
        ]);

        // B2.a PSN Fisik: kolom 0 aspek, 2 target akhir, 3 target kumulatif, 4 realisasi, 5 keterangan.
        $t = self::TABEL['B2A'];
        $this->tulis($this->sel($t, 'Progres fisik', 3), [$n($b['progres_rencana'])]);
        $this->tulis($this->sel($t, 'Progres fisik', 4), [$n($b['progres_realisasi'])]);
        if ($b['progres_realisasi'] !== null) {
            $this->tulis($this->sel($t, 'Progres fisik', 5), ["SIRENDA: progres tertimbang KP/RO tahun {$tahun}; sesuaikan dengan kurva-S proyek."], tambah: true);
        }
        $this->tulis($this->sel($t, 'Output utama', 5), [$ro], tambah: true);
        $this->tulis($this->sel($t, 'Pembiayaan', 2), [$n($b['pembiayaan_akhir'])]);
        $this->tulis($this->sel($t, 'Pembiayaan', 5), $biaya, tambah: true);

        // B2.b PSN Non-Fisik.
        $t = self::TABEL['B2B'];
        $this->tulis($this->sel($t, 'Output utama', 5), [$ro], tambah: true);
        $this->tulis($this->sel($t, 'Pembiayaan', 2), [$n($b['pembiayaan_akhir'])]);
        $this->tulis($this->sel($t, 'Pembiayaan', 5), $biaya, tambah: true);
        if ($b['regulasi_total']) {
            $this->tulis($this->sel($t, 'Regulasi', 2), [(string) $b['regulasi_total']]);
            $this->tulis($this->sel($t, 'Regulasi', 4), [(string) $b['regulasi_ditetapkan']]);
            if ($b['regulasi_belum']) {
                $this->tulis($this->sel($t, 'Regulasi', 5), ['SIRENDA, belum ditetapkan: '.implode('; ', $b['regulasi_belum'])], tambah: true);
            }
        }
    }

    /** C2: isu terbuka mengisi baris 1..n; baris tambahan disalin dari baris contoh terakhir. */
    private function isiC2(array $isu): void
    {
        $tbl = $this->tabel(self::TABEL['C2']);
        $baris = $this->xp->query('w:tr', $tbl);
        $contoh = $baris->item(3);          // baris "3"
        $penutup = $baris->item(4);         // baris "……"
        foreach ($isu as $i => $s) {
            if ($i < 3) {
                $tr = $baris->item($i + 1);
            } else {
                $tr = $contoh->cloneNode(true);
                $penutup->parentNode->insertBefore($tr, $penutup);
                $this->kosongkan($tr);
                foreach ($this->xp->query('.//w:sdt/w:sdtPr/w:id', $tr) as $id) {
                    $id->setAttributeNS(self::W, 'w:val', (string) $this->idSdt++);
                }
                $nomor = $this->xp->query('.//w:t', $this->xp->query('w:tc', $tr)->item(0))->item(0);
                $nomor && $nomor->nodeValue = (string) ($i + 1);
            }
            $sel = fn (int $k) => $this->xp->query('w:tc', $tr)->item($k);
            $this->tulis($sel(1), $this->baris($s['uraian']));
            $this->tulis($sel(3), $this->baris($s['upaya']));
            $this->tulis($sel(4), $this->baris($s['dukungan']));
            if ($s['jalur_kritis'] === true) {
                $this->centang($sel(2), 'Ya');
            }
        }
    }

    // ------------------------------------------------------------------ bantu DOM

    private function tabel(int $no): DOMElement
    {
        return $this->xp->query('/w:document/w:body/w:tbl')->item($no - 1) ?? throw new RuntimeException("Tabel {$no} tidak ada di templat.");
    }

    /** Sel kolom $kolom pada baris yang sel pertama (atau kedua) diawali $label. */
    private function sel(int $tabel, string $label, int $kolom): ?DOMElement
    {
        foreach ($this->xp->query('w:tr', $this->tabel($tabel)) as $tr) {
            $sel = $this->xp->query('w:tc', $tr);
            foreach ([0, 1] as $k) {
                if ($sel->item($k) && str_starts_with(trim($sel->item($k)->textContent), $label)) {
                    return $sel->item($kolom);
                }
            }
        }

        return null;
    }

    /**
     * Tulis teks biru ke sel. Bawaan: paragraf kosong pertama diisi (baris berikutnya menjadi paragraf baru).
     * $sambung: ditambahkan di akhir paragraf pertama (mis. setelah "Rp. "). $tambah: paragraf baru di akhir sel.
     */
    private function tulis(?DOMElement $tc, array $baris, bool $sambung = false, bool $tambah = false): void
    {
        $baris = array_values(array_filter($baris, fn ($b) => $b !== null && trim($b) !== ''));
        if (! $tc || ! $baris) {
            return;
        }
        $paragraf = $this->xp->query('w:p', $tc);
        $p = $paragraf->item(0);
        if ($sambung) {
            $p->appendChild($this->run(array_shift($baris)));
        } elseif (! $tambah && $p && trim($p->textContent) === '' && $this->xp->query('w:r|w:sdt', $p)->length === 0) {
            $p->appendChild($this->run(array_shift($baris)));
        }
        $acuan = $paragraf->item($paragraf->length - 1);
        foreach ($baris as $b) {
            $baru = $this->dok->createElementNS(self::W, 'w:p');
            if ($pPr = $this->xp->query('w:pPr', $acuan)->item(0)) {
                $baru->appendChild($pPr->cloneNode(true));
            }
            $baru->appendChild($this->run($b));
            $tc->appendChild($baru);
        }
    }

    private function run(string $teks): DOMElement
    {
        $r = $this->dok->createElementNS(self::W, 'w:r');
        $rPr = $r->appendChild($this->dok->createElementNS(self::W, 'w:rPr'));
        $warna = $rPr->appendChild($this->dok->createElementNS(self::W, 'w:color'));
        $warna->setAttributeNS(self::W, 'w:val', self::BIRU);
        $t = $r->appendChild($this->dok->createElementNS(self::W, 'w:t'));
        $t->setAttribute('xml:space', 'preserve');
        $t->appendChild($this->dok->createTextNode($teks));

        return $r;
    }

    /** Centang kotak (content control) yang berada tepat sebelum teks $label di dalam sel. */
    private function centang(?DOMElement $tc, string $label): void
    {
        if (! $tc) {
            return;
        }
        foreach ($this->xp->query('.//w:sdt[w:sdtPr/w14:checkbox]', $tc) as $sdt) {
            $setelah = '';
            for ($n = $sdt->nextSibling; $n && ! ($n instanceof DOMElement && $n->localName === 'sdt'); $n = $n->nextSibling) {
                $setelah .= $n->textContent;
            }
            if (str_starts_with(ltrim($setelah), $label)) {
                $this->xp->query('w:sdtPr/w14:checkbox/w14:checked', $sdt)->item(0)?->setAttributeNS(self::W14, 'w14:val', '1');
                foreach ($this->xp->query('w:sdtContent//w:t', $sdt) as $t) {
                    $t->nodeValue = '☒';
                }

                return;
            }
        }
    }

    /** Hapus run teks isian dan kosongkan kotak centang pada baris salinan. */
    private function kosongkan(DOMElement $tr): void
    {
        foreach ($this->xp->query('.//w:sdt[w:sdtPr/w14:checkbox]', $tr) as $sdt) {
            $this->xp->query('w:sdtPr/w14:checkbox/w14:checked', $sdt)->item(0)?->setAttributeNS(self::W14, 'w14:val', '0');
            foreach ($this->xp->query('w:sdtContent//w:t', $sdt) as $t) {
                $t->nodeValue = '☐';
            }
        }
        $sel = $this->xp->query('w:tc', $tr);
        foreach ([1, 3, 4] as $k) {
            foreach (iterator_to_array($this->xp->query('w:p/w:r', $sel->item($k))) as $r) {
                $r->parentNode->removeChild($r);
            }
        }
    }

    private function baris(?string $teks): array
    {
        return $teks === null ? [] : preg_split('/\R/', trim($teks));
    }
}
