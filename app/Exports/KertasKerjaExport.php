<?php

namespace App\Exports;

use App\Support\Evaluasi\FormatRingkas;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Kertas kerja pengendalian dalam tata letak sheet "Format Ringkas": header bertingkat (kelompok + kolom),
 * 1 baris = 1 PSN. Warna sel: biru muda = dari profil, kuning = wajib tetapi masih kosong, abu = otomatis.
 */
class KertasKerjaExport implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    protected const BARIS_AWAL = 5;

    public function __construct(protected FormatRingkas $format, protected Collection $baris) {}

    public function title(): string
    {
        return 'Format Ringkas';
    }

    public function array(): array
    {
        $kolom = $this->format->kolom();
        $isi = [
            ['Kertas Kerja Pengendalian PSN (format ringkas) RKP '.config('psn_dashboard.evaluasi_status.tahun_rkp').', diunduh '.now()->translatedFormat('j F Y H:i')],
            ['Legenda: biru muda = data profil SIRENDA; kuning = isian wajib masih kosong; abu = kolom otomatis; putih = isian evaluasi.'],
            array_map(fn () => null, $kolom),
            array_column($kolom, 'label'),
        ];
        $grup = [];
        foreach ($this->format->grup() as [$nama, $n]) {
            $grup[] = trim($nama);
            array_push($grup, ...array_fill(0, $n - 1, null));
        }
        $isi[2] = $grup;
        foreach ($this->baris as $b) {
            $isi[] = array_map(fn ($k) => $b['nilai'][$k['kunci']] ?? null, $kolom);
        }

        return $isi;
    }

    /**
     * Sheet "Daftar Pilihan" (sama dengan kertas kerja) dan validasi data pada kolom isian tunggal,
     * agar berkas dapat diisi luring dengan pilihan yang sama dengan aplikasi.
     */
    protected function daftarPilihan(Worksheet $s, int $barisAkhir): void
    {
        $c = config('psn_dashboard.evaluasi_status');
        $daftar = [
            'Jenis PSN' => $c['jenis_psn'], 'Tahapan' => $c['tahapan'], 'Sumber Pendanaan' => $c['sumber_pendanaan'], 'Status Milestone' => $c['status_milestone'],
            'Ya/Tidak' => $c['ya_tidak'], 'Centang' => ['Ya'], 'D1 Kebutuhan Status' => $c['d1'], 'D1 Alasan' => $c['d1_alasan'],
            'Jenis Lampiran' => $c['lampiran_jenis'], 'Bentuk Lampiran' => $c['lampiran_bentuk'], 'Hasil Pengecekan' => $c['hasil_pengecekan'],
            'Tindak Lanjut' => $c['tindak_lanjut'], 'Indikasi Status PSN' => $c['indikasi'], 'Status Akhir' => array_diff_key($c['status'], ['BELUM_DITERIMA' => 1]),
            'Milestone B3.a (Fisik)' => $c['milestone']['FISIK'], 'Milestone B3.b (Non-Fisik)' => $c['milestone']['NON_FISIK'],
            'Tipologi Isu C1' => array_map(fn ($k, $v) => "{$k}. {$v}", array_keys($c['tipologi_isu']), $c['tipologi_isu']),
            'Fasilitas D2 (PP 42/2021)' => array_map(fn ($i, $v) => ($i + 1).". {$v}", array_keys(array_values($c['d2_fasilitas'])), array_values($c['d2_fasilitas'])),
        ];
        $book = $s->getParent();
        $dp = $book->createSheet();
        $dp->setTitle('Daftar Pilihan');
        $dp->setCellValue('A1', 'Daftar pilihan isian (rujukan dropdown). Jangan diubah.');
        $rentang = [];
        $j = 1;
        foreach ($daftar as $judul => $opsi) {
            $huruf = Coordinate::stringFromColumnIndex($j);
            $dp->setCellValue("{$huruf}3", $judul);
            $dp->getColumnDimension($huruf)->setWidth(32);
            foreach (array_values($opsi) as $i => $v) {
                $dp->setCellValue($huruf.($i + 4), $v);
            }
            $rentang[$judul] = "'Daftar Pilihan'!\${$huruf}\$4:\${$huruf}\$".(count($opsi) + 3);
            $j++;
        }
        $dp->getStyle('A3:'.Coordinate::stringFromColumnIndex($j - 1).'3')->getFont()->setBold(true);

        $kolom = array_flip(array_column($this->format->kolom(), 'kunci'));
        $daftarUntuk = ['a8' => 'Sumber Pendanaan', 'a12' => 'Jenis PSN', 'b1' => 'Tahapan', 'd3_tahapan' => 'Tahapan', 'd1' => 'D1 Kebutuhan Status',
            'd1_alasan' => 'D1 Alasan', 'hasil' => 'Hasil Pengecekan', 'tl' => 'Tindak Lanjut', 'indikasi' => 'Indikasi Status PSN'];
        foreach ($daftarUntuk as $kunci => $judul) {
            $huruf = Coordinate::stringFromColumnIndex($kolom[$kunci] + 1);
            $dv = new DataValidation;
            $dv->setType(DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)
                ->setErrorTitle('Pilihan tidak valid')->setError('Pilih dari daftar (sheet Daftar Pilihan).')->setFormula1($rentang[$judul]);
            $dv->setSqref("{$huruf}".self::BARIS_AWAL.":{$huruf}{$barisAkhir}");
            $s->setDataValidation("{$huruf}".self::BARIS_AWAL.":{$huruf}{$barisAkhir}", $dv);
        }
        $c2 = config('psn_dashboard.evaluasi_status');
        foreach (['a6', 'a7', 'd3_tahun'] as $kunci) {
            $huruf = Coordinate::stringFromColumnIndex($kolom[$kunci] + 1);
            $dv = new DataValidation;
            $dv->setType(DataValidation::TYPE_WHOLE)->setOperator(DataValidation::OPERATOR_BETWEEN)->setAllowBlank(true)->setShowErrorMessage(true)
                ->setErrorTitle('Tahun tidak valid')->setError("Isi tahun {$c2['tahun_min']} sampai {$c2['tahun_maks']}.")
                ->setFormula1((string) $c2['tahun_min'])->setFormula2((string) $c2['tahun_maks']);
            $s->setDataValidation("{$huruf}".self::BARIS_AWAL.":{$huruf}{$barisAkhir}", $dv);
        }
        $book->setActiveSheetIndex(0);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $ev) {
            $s = $ev->sheet->getDelegate();
            $kolom = $this->format->kolom();
            $akhir = Coordinate::stringFromColumnIndex(count($kolom));
            $s->getStyle('A1')->getFont()->setBold(true)->setSize(13);
            $s->getStyle('A2')->getFont()->setItalic(true);
            $i = 1;
            foreach ($this->format->grup() as [, $n]) {
                $n > 1 && $s->mergeCells(Coordinate::stringFromColumnIndex($i).'3:'.Coordinate::stringFromColumnIndex($i + $n - 1).'3');
                $i += $n;
            }
            $hdr = $s->getStyle("A3:{$akhir}4");
            $hdr->getFont()->setBold(true);
            $hdr->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF7');
            $hdr->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
            foreach ($kolom as $j => $k) {
                $huruf = Coordinate::stringFromColumnIndex($j + 1);
                $s->getColumnDimension($huruf)->setWidth(in_array($k['kunci'], ['no', 'a6', 'a7', 'd3_tahun'], true) ? 8 : (str_contains($k['kunci'], 'keterangan') || in_array($k['kunci'], ['a4', 'a10', 'c2_uraian', 'c2_dukungan', 'catatan', 'b3a_jenis', 'b3b_jenis'], true) ? 42 : 18));
                $k['wajib'] && $s->getStyle("{$huruf}4")->getFont()->getColor()->setRGB('9A3412');
            }
            $r = self::BARIS_AWAL;
            foreach ($this->baris as $b) {
                foreach ($kolom as $j => $k) {
                    $sel = Coordinate::stringFromColumnIndex($j + 1).$r;
                    $warna = in_array($k['kunci'], $b['kurang'], true) ? 'FEF3C7' : match ($b['asal'][$k['kunci']] ?? 'ISIAN') {
                        'PROFIL' => 'EAF4FF', 'OTOMATIS' => 'F1F5F9', default => null,
                    };
                    $warna && $s->getStyle($sel)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($warna);
                }
                $r++;
            }
            $s->getStyle('A'.self::BARIS_AWAL.":{$akhir}".max(self::BARIS_AWAL, $r - 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $this->daftarPilihan($s, max(self::BARIS_AWAL, $r - 1) + 200);
            $s->freezePane('D'.self::BARIS_AWAL);
            $s->setAutoFilter("A4:{$akhir}".max(4, $r - 1));
        }];
    }
}
