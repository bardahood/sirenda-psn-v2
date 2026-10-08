<?php

namespace App\Support\Dashboard;

use App\Enums\StatusProgres;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Filter global dashboard yang dibawa lewat query string dan berlaku lintas halaman:
 * periode=2026-09&prov=31,32&klaster=3,7&dit=12&status=terlambat&kat=psn&dana=kpbu
 */
final class FilterGlobal
{
    public const SKEMA_DANA = ['apbn', 'apbd', 'kpbu', 'lainnya'];

    /**
     * @param  string[]  $prov  kode provinsi
     * @param  int[]  $klaster  ref_klaster.id
     * @param  int[]  $dit  ref_unit_kerja.id
     * @param  string[]  $status  StatusProgres value
     * @param  string[]  $dana  skema (APBN|APBD|KPBU|LAINNYA)
     */
    public function __construct(
        public readonly ?string $periode = null,
        public readonly array $prov = [],
        public readonly array $klaster = [],
        public readonly array $dit = [],
        public readonly array $status = [],
        public readonly ?string $kat = null,
        public readonly array $dana = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        $daftar = fn (string $k) => array_values(array_filter(array_map('trim', explode(',', (string) $request->query($k, ''))), fn ($v) => $v !== ''));

        $input = [
            'periode' => $request->query('periode') ?: null,
            'prov' => $daftar('prov'),
            'klaster' => $daftar('klaster'),
            'dit' => $daftar('dit'),
            'status' => array_map('strtolower', $daftar('status')),
            'kat' => $request->query('kat') ? strtolower($request->query('kat')) : null,
            'dana' => array_map('strtolower', $daftar('dana')),
        ];

        Validator::make($input, [
            'periode' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'prov.*' => ['regex:/^\d{2}$/'],
            'klaster.*' => ['integer', 'min:1'],
            'dit.*' => ['integer', 'min:1'],
            'status.*' => ['in:'.implode(',', array_map(fn ($s) => strtolower($s->value), StatusProgres::cases()))],
            'kat' => ['nullable', 'in:psn,pkpn'],
            'dana.*' => ['in:'.implode(',', self::SKEMA_DANA)],
        ], [
            'periode.regex' => 'Format periode harus YYYY-MM.',
            '*.*.regex' => 'Kode provinsi tidak valid.',
            '*.*.in' => 'Nilai filter tidak dikenal.',
            '*.*.integer' => 'Nilai filter harus berupa angka.',
        ])->validate();

        return new self(
            $input['periode'],
            array_values(array_unique($input['prov'])),
            array_values(array_unique(array_map('intval', $input['klaster']))),
            array_values(array_unique(array_map('intval', $input['dit']))),
            array_values(array_unique(array_map('strtoupper', $input['status']))),
            $input['kat'] ? strtoupper($input['kat']) : null,
            array_values(array_unique(array_map('strtoupper', $input['dana']))),
        );
    }

    /** Salinan dengan periode berbeda (mis. untuk pembanding cut-off sebelumnya). */
    public function denganPeriode(?string $periode): self
    {
        return new self($periode, $this->prov, $this->klaster, $this->dit, $this->status, $this->kat, $this->dana);
    }

    /**
     * Apakah satu baris snapshot_psn lolos filter. Kolom JSON multi-nilai (provinsi,
     * unit, sumber dana) lolos bila beririsan dengan pilihan filter.
     *
     * @param  array<int,string>  $skemaPerDanaId  ref_sumber_dana.id => skema
     */
    public function cocok(object $row, array $skemaPerDanaId): bool
    {
        $irisan = fn (array $pilihan, array $nilai) => ! $pilihan || array_intersect($pilihan, $nilai);

        return $irisan($this->prov, (array) $row->provinsi_kode)
            && (! $this->klaster || in_array((int) $row->klaster_id, $this->klaster, true))
            && $irisan($this->dit, array_map('intval', (array) $row->unit_kerja_id))
            && (! $this->status || in_array($row->status_progres, $this->status, true))
            && (! $this->kat || $row->kategori === $this->kat)
            && $irisan($this->dana, array_map(fn ($id) => $skemaPerDanaId[$id] ?? 'LAINNYA', (array) $row->sumber_dana_id));
    }

    /** Bentuk kanonik untuk meta respons dan query string (hanya yang terisi). */
    public function toArray(): array
    {
        return array_filter([
            'periode' => $this->periode,
            'prov' => $this->prov ? implode(',', $this->prov) : null,
            'klaster' => $this->klaster ? implode(',', $this->klaster) : null,
            'dit' => $this->dit ? implode(',', $this->dit) : null,
            'status' => $this->status ? strtolower(implode(',', $this->status)) : null,
            'kat' => $this->kat ? strtolower($this->kat) : null,
            'dana' => $this->dana ? strtolower(implode(',', $this->dana)) : null,
        ]);
    }
}
