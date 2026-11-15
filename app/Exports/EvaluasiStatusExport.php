<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Ekspor daftar evaluasi status PSN (kolom mengikuti "Format Ringkas" kertas kerja + hasil analisis sistem). */
class EvaluasiStatusExport implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(protected Collection $baris) {}

    public function title(): string
    {
        return 'Evaluasi Status PSN';
    }

    public function headings(): array
    {
        return ['No', 'Kode PSN', 'Nama PSN', 'Sumber', 'Klaster', 'Tahapan (B1)', 'Target tahun selesai (A7)', 'Tahun penetapan (A6)',
            'Progres rencana (%)', 'Progres realisasi (%)', 'Deviasi fisik (poin)', 'Serapan anggaran (%)', 'Isu terbuka', 'Isu jalur kritis',
            'Form diterima', 'Isian wajib yang belum terisi', 'Status kelengkapan', 'D1 Kebutuhan status PSN', 'D1 Alasan', 'D2 Fasilitas', 'D3 Tahapan target',
            'D3 Target', 'D3 Proyeksi tahun selesai', 'Hasil Pengecekan', 'Tindak Lanjut', 'Catatan Hasil Analisis', 'Indikasi Status PSN', 'Status Akhir',
            'Rekomendasi sistem', 'Sinyal analisis'];
    }

    public function collection(): Collection
    {
        $c = config('psn_dashboard.evaluasi_status');
        $l = fn (string $grup, $kode) => $kode ? ($c[$grup][$kode] ?? $kode) : null;

        return $this->baris->values()->map(function ($r, $i) use ($c, $l) {
            $e = $r['evaluasi'];
            $d = $r['data'];

            return [$i + 1, $r['kode'], $r['nama'], $r['sumber'], $r['klaster'], $l('tahapan', $d['tahapan']), $d['tahun_selesai'], $e?->a6_tahun_penetapan,
                $d['progres_rencana'], $d['progres_realisasi'], $r['indikator']['deviasi_fisik'], $r['indikator']['serapan'], $d['isu_terbuka'], $d['isu_jalur_kritis'],
                $e?->form_diterima_at?->format('Y-m-d'), implode('; ', $r['kurang']), $r['lengkap'] ? 'Lengkap' : 'Belum Lengkap',
                $l('d1', $e?->d1_kebutuhan), $e?->d1_alasan === 'LAINNYA' ? $e->d1_alasan_lain : $l('d1_alasan', $e?->d1_alasan),
                collect($e?->d2_fasilitas ?? [])->map(fn ($k) => $c['d2_fasilitas'][$k] ?? $k)->implode('; '), $l('tahapan', $e?->d3_tahapan), $e?->d3_target, $e?->d3_tahun_selesai,
                $l('hasil_pengecekan', $e?->hasil_pengecekan), $l('tindak_lanjut', $e?->tindak_lanjut), $e?->catatan_analisis, $l('indikasi', $e?->indikasi_status),
                $c['status'][$r['status_akhir']], $c['rekomendasi'][$r['rekomendasi']], implode('; ', $r['sinyal'])];
        });
    }
}
