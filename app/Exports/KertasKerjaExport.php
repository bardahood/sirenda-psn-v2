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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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
            $s->freezePane('D'.self::BARIS_AWAL);
            $s->setAutoFilter("A4:{$akhir}".max(4, $r - 1));
        }];
    }
}
