<?php

namespace App\Exports;

use App\Models\PeriodeCutoff;
use App\Services\RisikoService;
use App\Support\Dashboard\FilterGlobal;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Ekspor Excel register risiko per cut-off: baris & urutan identik dengan register di /risiko. */
class RegisterRisikoExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    public function __construct(protected PeriodeCutoff $c, protected FilterGlobal $f) {}

    public function query(): Builder
    {
        return app(RisikoService::class)->registerQuery($this->c, $this->f, []);
    }

    public function headings(): array
    {
        return ['ID risiko', 'ID PSN', 'PSN', 'Uraian risiko', 'Kategori', 'Kemungkinan harapan', 'Dampak harapan', 'Level harapan',
            'Kemungkinan aktual', 'Dampak aktual', 'Level aktual', 'Skor residual', 'Penanggung jawab', 'Rencana perlakuan'];
    }

    public function map($row): array
    {
        $b = app(RisikoService::class)->barisRegister($row);

        return [$b['risiko_id'], $b['psn_id'], $b['psn'], $b['uraian'], $b['kategori'], $b['harapan']['kemungkinan'], $b['harapan']['dampak'], $b['harapan']['level'],
            $b['aktual']['kemungkinan'], $b['aktual']['dampak'], $b['aktual']['level'], $b['skor_residual'], $b['pic'], $b['mitigasi']];
    }

    public function title(): string
    {
        return 'Register Risiko '.$this->c->kode;
    }
}
