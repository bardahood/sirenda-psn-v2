<?php

namespace App\Exports;

use App\Models\PeriodeCutoff;
use App\Services\PortofolioService;
use App\Support\Dashboard\FilterGlobal;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Ekspor Excel Portofolio PSN: baris & filter identik dengan tabel /proyek. */
class PortofolioExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    public function __construct(protected PeriodeCutoff $c, protected FilterGlobal $f, protected array $opsi) {}

    public function query(): Builder
    {
        return app(PortofolioService::class)->query($this->c, $this->f, $this->opsi);
    }

    public function headings(): array
    {
        return ['ID', 'Kode', 'Nama PSN', 'Klaster', 'Tahap', 'Kategori', 'Provinsi', 'Investasi (Rp triliun)', 'Investasi anomali',
            'Rencana (%)', 'Realisasi (%)', 'Deviasi (pp)', 'Status progres', 'Risiko kritis', 'Level risiko', 'Kelengkapan (%)', 'Pembaruan terakhir', 'Tahun selesai'];
    }

    public function map($row): array
    {
        $b = app(PortofolioService::class)->baris($row);

        return [$b['id'], $b['kode'], $b['nama'], $b['klaster'], $b['tahap'], $b['kategori'], implode(', ', $b['provinsi']), $b['investasi_triliun'],
            $b['investasi_anomali'] ? 'ya' : '', $b['rencana_persen'], $b['realisasi_persen'], $b['deviasi_pp'], $b['status_progres'],
            $b['is_kritis'] ? 'ya' : '', $b['risiko_level'], $b['kelengkapan_persen'], $b['pembaruan_terakhir'], $b['tahun_selesai']];
    }

    public function title(): string
    {
        return 'Portofolio '.$this->c->kode;
    }
}
