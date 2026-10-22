<?php

namespace App\Support\Dashboard;

use App\Enums\StatusProgres;
use App\Support\Referensi\Kementerian;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Filter global dashboard yang dibawa lewat query string dan berlaku lintas halaman:
 * periode=2026-09&prov=31,32&klaster=3,7&dit=12&status=terlambat&kat=psn&dana=kpbu&psn=12,40
 * &kl=kementerian-perhubungan&thn=2029,2030
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
     * @param  int[]  $psn  psn.id (pilih PSN tertentu)
     * @param  string[]  $kl  slug kementerian/lembaga induk (App\Support\Referensi\Kementerian)
     * @param  int[]  $klUnit  ref_unit_kerja.id anggota $kl (hasil ekspansi)
     * @param  int[]  $thn  tahun target selesai
     */
    public function __construct(
        public readonly ?string $periode = null,
        public readonly array $prov = [],
        public readonly array $klaster = [],
        public readonly array $dit = [],
        public readonly array $status = [],
        public readonly ?string $kat = null,
        public readonly array $dana = [],
        public readonly array $psn = [],
        public readonly array $kl = [],
        public readonly array $klUnit = [],
        public readonly array $thn = [],
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
            'psn' => $daftar('psn'),
            'kl' => array_map('strtolower', $daftar('kl')),
            'thn' => $daftar('thn'),
        ];

        Validator::make($input, [
            'periode' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'prov.*' => ['regex:/^\d{2}$/'],
            'klaster.*' => ['integer', 'min:1'],
            'dit.*' => ['integer', 'min:1'],
            'status.*' => ['in:'.implode(',', array_map(fn ($s) => strtolower($s->value), StatusProgres::cases()))],
            'kat' => ['nullable', 'in:psn,pkpn'],
            'dana.*' => ['in:'.implode(',', self::SKEMA_DANA)],
            'psn' => ['array', 'max:100'],
            'psn.*' => ['integer', 'min:1'],
            'kl' => ['array', 'max:50'],
            'kl.*' => ['regex:/^[a-z0-9-]{1,120}$/'],
            'thn' => ['array', 'max:50'],
            'thn.*' => ['integer', 'between:1900,2100'],
        ], [
            'periode.regex' => 'Format periode harus YYYY-MM.',
            '*.*.regex' => 'Kode provinsi tidak valid.',
            '*.*.in' => 'Nilai filter tidak dikenal.',
            '*.*.integer' => 'Nilai filter harus berupa angka.',
            'thn.*.between' => 'Tahun tidak valid.',
        ])->validate();

        return new self(
            $input['periode'],
            array_values(array_unique($input['prov'])),
            array_values(array_unique(array_map('intval', $input['klaster']))),
            array_values(array_unique(array_map('intval', $input['dit']))),
            array_values(array_unique(array_map('strtoupper', $input['status']))),
            $input['kat'] ? strtoupper($input['kat']) : null,
            array_values(array_unique(array_map('strtoupper', $input['dana']))),
            array_values(array_unique(array_map('intval', $input['psn']))),
            $kl = array_values(array_unique($input['kl'])),
            Kementerian::unit($kl),
            array_values(array_unique(array_map('intval', $input['thn']))),
        );
    }

    /** Salinan dengan periode berbeda (mis. untuk pembanding cut-off sebelumnya). */
    public function denganPeriode(?string $periode): self
    {
        return new self($periode, $this->prov, $this->klaster, $this->dit, $this->status, $this->kat, $this->dana, $this->psn, $this->kl, $this->klUnit, $this->thn);
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

        return (! $this->psn || in_array((int) $row->psn_id, $this->psn, true))
            && $irisan($this->prov, (array) $row->provinsi_kode)
            && (! $this->klaster || in_array((int) $row->klaster_id, $this->klaster, true))
            && $irisan($this->dit, array_map('intval', (array) $row->unit_kerja_id))
            && (! $this->kl || array_intersect($this->klUnit, array_map('intval', (array) $row->unit_kerja_id)))
            && (! $this->thn || in_array((int) $row->tahun_selesai, $this->thn, true))
            && (! $this->status || in_array($row->status_progres, $this->status, true))
            && (! $this->kat || $row->kategori === $this->kat)
            && $irisan($this->dana, array_map(fn ($id) => $skemaPerDanaId[$id] ?? 'LAINNYA', (array) $row->sumber_dana_id));
    }

    /**
     * Padanan SQL dari cocok() untuk query bertabel snapshot_psn (paginasi server).
     * Kolom JSON multi-nilai memakai JSON_CONTAINS (tersedia di MySQL 8 & MariaDB 10.6+).
     *
     * @param  array<int,string>  $skemaPerDanaId  ref_sumber_dana.id => skema
     */
    public function terapkanSql(Builder|EloquentBuilder $q, array $skemaPerDanaId, string $t = 'snapshot_psn'): void
    {
        $jsonSalahSatu = function (string $kolom, array $nilai, bool $teks) use ($q, $t) {
            if (! $nilai) {
                return;
            }
            $q->where(function ($w) use ($kolom, $nilai, $teks, $t) {
                foreach ($nilai as $v) {
                    $w->orWhereRaw("JSON_CONTAINS({$t}.{$kolom}, ?)", [$teks ? json_encode((string) $v) : (string) (int) $v]);
                }
            });
        };

        $jsonSalahSatu('provinsi_kode', $this->prov, true);
        $jsonSalahSatu('unit_kerja_id', $this->dit, false);
        if ($this->kl) {
            $this->klUnit ? $jsonSalahSatu('unit_kerja_id', $this->klUnit, false) : $q->whereRaw('1 = 0');
        }
        $jsonSalahSatu('sumber_dana_id', array_keys(array_filter($skemaPerDanaId, fn ($s) => in_array($s, $this->dana, true))), false);
        if ($this->dana && ! array_intersect($this->dana, $skemaPerDanaId)) {
            $q->whereRaw('1 = 0');
        }

        $this->klaster && $q->whereIn("{$t}.klaster_id", $this->klaster);
        $this->status && $q->whereIn("{$t}.status_progres", $this->status);
        $this->kat && $q->where("{$t}.kategori", $this->kat);
        $this->psn && $q->whereIn("{$t}.psn_id", $this->psn);
        $this->thn && $q->whereIn("{$t}.tahun_selesai", $this->thn);
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
            'psn' => $this->psn ? implode(',', $this->psn) : null,
            'kl' => $this->kl ? implode(',', $this->kl) : null,
            'thn' => $this->thn ? implode(',', $this->thn) : null,
        ]);
    }
}
